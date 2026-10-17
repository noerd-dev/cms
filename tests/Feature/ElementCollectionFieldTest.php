<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);
    DatabaseCollectionDefinitionRepository::resetCache();
    $this->createElementFixtures();
});

afterEach(function (): void {
    $this->removeElementFixtures();
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
    $element = ContentElement::create(['content_id' => $page->content_id, 'element_key' => 'team_section', 'data' => [], 'sort' => 0]);

    Livewire::test('element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_ELEMENT_PAGE,
        'ownerId' => $element->id,
        'fieldName' => 'members',
        'label' => 'Team members',
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

it('counts the element-collection entries of a saved element for the document editor', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Startseite']]);
    $element = ContentElement::create(['content_id' => $page->content_id, 'element_key' => $this->zzCollectionElementKey(), 'data' => [], 'sort' => 0]);

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

    $config = Livewire::withUrlParams(['pageId' => $page->id])->test('cms::page-detail')->instance()->contentDocumentConfig();

    expect((array) $config['collectionCounts'])->toBe(['r' . $element->id => ['items' => 2]]);
});

it('opens the entries of an element collection from the document editor', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Startseite']]);
    $element = ContentElement::create(['content_id' => $page->content_id, 'element_key' => $this->zzCollectionElementKey(), 'data' => [], 'sort' => 0]);

    Livewire::withUrlParams(['pageId' => $page->id])->test('cms::page-detail')
        ->call('manageContentDocumentCollection', 'r' . $element->id, 'items')
        ->assertDispatched('noerdModal');

    expect(Collection::query()->where('element_page_id', $element->id)->where('owner_field', 'items')->exists())->toBeTrue();
});

it('opens no element collection for an element of another page', function (): void {
    $own = Page::factory()->create(['tenant_id' => $this->tenant->id]);
    $other = Page::factory()->create(['tenant_id' => $this->tenant->id]);
    $foreign = ContentElement::create(['content_id' => $other->content_id, 'element_key' => $this->zzCollectionElementKey(), 'data' => [], 'sort' => 0]);

    Livewire::withUrlParams(['pageId' => $own->id])->test('cms::page-detail')
        ->call('manageContentDocumentCollection', 'r' . $foreign->id, 'items')
        ->assertNotDispatched('noerdModal');

    expect(Collection::query()->where('element_page_id', $foreign->id)->exists())->toBeFalse();
});
