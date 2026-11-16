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
use Noerd\Cms\Tests\Traits\CreatesCollectionDefinitions;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class, CreatesCollectionDefinitions::class);

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
    Livewire::test('cms::element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => null,
        'fieldName' => 'triggers_items',
        'label' => 'Triggers — Items',
        'rowFields' => [['name' => 'text', 'type' => 'translatableTextarea']],
    ])->assertSee(__('Please save the entry first to manage its entries.'));
});

it('creates a page-owned element collection and opens the modal on manage()', function (): void {
    $collection = $this->zzCollectionDefinition($this->tenant->id, 'demos', [
        ['name' => 'detailData.title', 'label' => 'Title', 'type' => 'translatableText', 'colspan' => 12],
        [
            'name' => 'detailData.triggers_items',
            'label' => 'Triggers — Items',
            'type' => 'elementCollection',
            'colspan' => 12,
            'fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        ],
    ]);
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo'], 'collection_id' => $collection->id]);

    Livewire::test('cms::element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => $owner->id,
        'fieldName' => 'triggers_items',
        'label' => 'Triggers — Items',
        // The schema the client passes is ignored: the definition's rows count.
        'rowFields' => [['name' => 'injected', 'type' => 'text']],
    ])
        ->assertSee(__('Manage'))
        ->call('manage')
        ->assertDispatched('noerdModal');

    $elementCollection = Collection::where('page_id', $owner->id)
        ->where('is_element_collection', true)
        ->where('owner_field', 'triggers_items')
        ->first();

    expect($elementCollection)->not->toBeNull()
        ->and(array_column($elementCollection->element_fields, 'name'))->toBe(['detailData.text']);
});

it('creates an element_page-owned element collection on manage()', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Page']]);
    $element = ContentElement::create(['content_id' => $page->content_id, 'element_key' => $this->zzCollectionElementKey(), 'data' => [], 'sort' => 0]);

    Livewire::test('cms::element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_ELEMENT_PAGE,
        'ownerId' => $element->id,
        'fieldName' => 'items',
        'label' => 'Items',
        'rowFields' => [['name' => 'injected', 'type' => 'translatableText']],
    ])
        ->assertSee(__('Manage'))
        ->call('manage')
        ->assertDispatched('noerdModal');

    $elementCollection = Collection::where('element_page_id', $element->id)
        ->where('is_element_collection', true)
        ->where('owner_field', 'items')
        ->first();

    expect($elementCollection)->not->toBeNull()
        ->and(array_column($elementCollection->element_fields, 'name'))->toBe(['detailData.image']);
});

it('creates no element collection for a field the owner does not declare', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Page']]);
    $element = ContentElement::create(['content_id' => $page->content_id, 'element_key' => $this->zzCollectionElementKey(), 'data' => [], 'sort' => 0]);
    $plainOwner = Page::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test('cms::element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_ELEMENT_PAGE,
        'ownerId' => $element->id,
        'fieldName' => 'headline',
        'rowFields' => [['name' => 'evil', 'type' => 'text']],
    ])->call('manage')->assertNotDispatched('noerdModal');

    Livewire::test('cms::element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => $plainOwner->id,
        'fieldName' => 'anything',
        'rowFields' => [['name' => 'evil', 'type' => 'text']],
    ])->call('manage')->assertNotDispatched('noerdModal');

    expect(Collection::where('is_element_collection', true)->count())->toBe(0);
});

it('creates no element collection for an owner of another tenant', function (): void {
    $foreignTenant = Noerd\Models\Tenant::factory()->create();
    $foreignPage = Page::factory()->create(['tenant_id' => $foreignTenant->id]);
    $foreignElement = ContentElement::create(['content_id' => $foreignPage->content_id, 'element_key' => $this->zzCollectionElementKey(), 'data' => [], 'sort' => 0]);

    Livewire::test('cms::element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_ELEMENT_PAGE,
        'ownerId' => $foreignElement->id,
        'fieldName' => 'items',
    ])->call('manage')->assertNotDispatched('noerdModal');

    expect(Collection::query()->acrossTenants()->where('element_page_id', $foreignElement->id)->exists())->toBeFalse();
});

it('refuses the field for a user without the CMS app', function (): void {
    $outsider = createNoerdUserForApp('ZZOTHER');
    $this->actingAs($outsider, Noerd\Helpers\NoerdAuth::guardName());

    $page = Page::factory()->create(['tenant_id' => $outsider->selected_tenant_id]);

    Livewire::test('cms::element-collection-field', [
        'ownerType' => ElementCollectionService::OWNER_PAGE,
        'ownerId' => $page->id,
        'fieldName' => 'items',
    ])->assertForbidden();
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
                'type' => 'elementCollection',
                'colspan' => 12,
                'fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
            ],
            [
                'name' => 'detailData.activities_items',
                'label' => 'Aktivitäten',
                'type' => 'elementCollection',
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
        ->test('cms::page-detail', ['collectionKey' => 'services'])
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
