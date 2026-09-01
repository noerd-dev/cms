<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Services\FieldTypeRegistry;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);
    DatabaseCollectionDefinitionRepository::resetCache();
});

it('shows a save-first hint when the owner is not yet saved', function (): void {
    Livewire::test('element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => null,
        'fieldName' => 'triggers_items',
        'label' => 'Triggers — Items',
        'rowFields' => [['name' => 'text', 'type' => 'translatableTextarea']],
    ])->assertSee(__('Please save the entry first to manage its entries.'));
});

it('creates a page-owned element collection and opens the modal on manage()', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);

    Livewire::test('element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => $owner->id,
        'fieldName' => 'triggers_items',
        'label' => 'Triggers — Items',
        'rowFields' => [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
    ])
        ->assertSee(__('Manage'))
        ->call('manage')
        ->assertDispatched('noerdModal');

    expect(
        Collection::where('page_id', $owner->id)
            ->where('is_element_collection', true)
            ->where('owner_field', 'triggers_items')
            ->exists(),
    )->toBeTrue();
});

it('creates an element_page-owned element collection on manage()', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Page']]);
    $element = ElementPage::create(['page_id' => $page->id, 'element_key' => 'team_section', 'data' => '{}', 'sort' => 0]);

    Livewire::test('element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_ELEMENT_PAGE,
        'ownerId' => $element->id,
        'fieldName' => 'members',
        'label' => 'Team-Mitglieder',
        'rowFields' => [['name' => 'name', 'type' => 'translatableText']],
    ])
        ->assertSee(__('Manage'))
        ->call('manage')
        ->assertDispatched('noerdModal');

    expect(
        Collection::where('element_page_id', $element->id)
            ->where('is_element_collection', true)
            ->where('owner_field', 'members')
            ->exists(),
    )->toBeTrue();
});

it('renders the element-collection field through the entry editor instead of [object Object]', function (): void {
    // The entry editor resolves its fields from the collection definition row, so the
    // SERVICES definition has to exist before the Livewire component is mounted.
    CollectionDefinition::create([
        'tenant_id' => $this->tenant->id,
        'filename' => 'services',
        'key' => 'SERVICES',
        'title' => 'Leistung',
        'title_list' => 'Leistungen',
        'has_page' => true,
        'fields' => [
            ['name' => 'detailData.title', 'label' => 'Titel', 'type' => 'translatableText', 'colspan' => 12],
            [
                'name' => 'detailData.triggers_items',
                'label' => 'Triggers',
                'type' => 'element-collection',
                'colspan' => 12,
                'fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
            ],
            [
                'name' => 'detailData.activities_items',
                'label' => 'Aktivitäten',
                'type' => 'element-collection',
                'colspan' => 12,
                'fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
            ],
        ],
    ]);

    $services = Collection::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'collection_key' => 'SERVICES'],
        ['name' => 'Leistungen', 'is_element_collection' => false],
    );

    $entry = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $services->id,
        'name' => ['de' => 'Demo', 'en' => 'Demo'],
        'data' => [],
    ]);

    // The entry editor renders the SERVICES fields (e.g. the "Titel" field) and the
    // triggers/activities fields no longer stringify their array into "[object Object]".
    // pageId is the URL alias of $modelId (see NoerdPage::queryStringNoerdPage), so it
    // has to arrive as a query param — as a mount arg the editor would open blank and
    // the element-collection field would only show its "save first" hint.
    Livewire::withQueryParams(['pageId' => $entry->id])
        ->test('page-detail', ['collectionKey' => 'services'])
        ->assertSee('Titel')
        ->assertSee('Triggers')
        ->assertSee(__('Manage'))
        ->assertDontSee('[object Object]');
});

it('resolves the element-collection owner to the element page inside the element editor', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Startseite']]);
    $element = ElementPage::create(['page_id' => $page->id, 'element_key' => 'gallery_grid', 'data' => '{}', 'sort' => 0]);

    $editor = Livewire::test('element-page-detail', ['modelId' => $element->id])->instance();

    $props = app(FieldTypeRegistry::class)
        ->resolve('element-collection')
        ->resolveProps(
            ['name' => 'detailData.images', 'label' => 'Bilder', 'fields' => []],
            $editor,
            [],
            $element->id,
        );

    expect($props['ownerType'])->toBe(ElementCollectionService::OWNER_ELEMENT_PAGE);
});

it('counts existing element-collection entries in the element editor', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Startseite']]);
    // element_collection_test is the fixture element that declares an
    // `element-collection` field (`items`); a key without a matching
    // .blade.php renders the "component not found" box instead of the editor.
    $element = ElementPage::create(['page_id' => $page->id, 'element_key' => 'element_collection_test', 'data' => '{}', 'sort' => 0]);

    $elementCollection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_ELEMENT_PAGE,
        $element->id,
        $this->tenant->id,
        'items',
        [['name' => 'image', 'label' => 'Bild', 'type' => 'image', 'colspan' => 12]],
        'Startseite: Projektfotos',
    );

    foreach (['/img/a.jpg', '/img/b.jpg'] as $sort => $image) {
        Page::factory()->create([
            'tenant_id' => $this->tenant->id,
            'collection_id' => $elementCollection->id,
            'data' => ['image' => $image],
            'sort' => $sort,
        ]);
    }

    Livewire::test('element-page-detail', ['modelId' => $element->id])
        ->assertSee('2 ' . trans_choice('Entry|Entries', 2));
});
