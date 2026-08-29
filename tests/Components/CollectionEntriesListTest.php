<?php

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    session()->forget('selectedLanguage');
    session()->forget('listFilters');
    DatabaseCollectionDefinitionRepository::resetCache();
});

/**
 * Seed a definition plus its parent collection row for the current tenant.
 *
 * @param  array<int, array<string, mixed>>  $fields
 */
function entriesListDefinition(int $tenantId, string $filename, array $fields, bool $hasPage = false): Collection
{
    $key = mb_strtoupper(str_replace('-', '_', $filename));

    CollectionDefinition::create([
        'tenant_id' => $tenantId,
        'filename' => $filename,
        'key' => $key,
        'title' => ucfirst($filename),
        'title_list' => ucfirst($filename),
        'has_page' => $hasPage,
        'fields' => $fields,
    ]);

    return Collection::create([
        'tenant_id' => $tenantId,
        'collection_key' => $key,
        'name' => ucfirst($filename),
    ]);
}

function useGermanOnly(int $tenantId): void
{
    CmsLanguage::where('tenant_id', $tenantId)->delete();
    CmsLanguage::create(['tenant_id' => $tenantId, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);
}

it('resolves the collection key from string, numeric id and numeric-string id', function (string $inputType): void {
    $collection = Collection::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_key' => 'STANDORT',
    ]);

    $input = match ($inputType) {
        'string key' => 'standort',
        'numeric id' => $collection->id,
        'numeric-string id' => (string) $collection->id,
    };

    Livewire::test('cms::collection-entries-list', ['collectionKey' => $input])
        ->assertSet('collectionKey', 'standort')
        ->assertStatus(200);
})->with(['string key', 'numeric id', 'numeric-string id']);

it('does not resolve a numeric id belonging to another tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $foreign = Collection::factory()->create([
        'tenant_id' => $otherTenant->id,
        'collection_key' => 'FREMD',
    ]);

    Livewire::test('cms::collection-entries-list', ['collectionKey' => $foreign->id])
        ->assertSet('collectionKey', null)
        ->assertSee(__('Please select a collection from the navigation.'));
});

it('initializes the collection from the id in the collection query parameter', function (): void {
    $collection = entriesListDefinition($this->tenant->id, 'standort', [
        ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text', 'colspan' => 6],
    ]);

    Livewire::withQueryParams(['collection' => $collection->id])
        ->test('cms::collection-entries-list')
        ->assertSet('collectionKey', 'standort')
        ->assertSet('collectionId', $collection->id)
        ->assertStatus(200);
});

it('mirrors the collection row id into collectionId when opened with an element key', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);

    $elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'Zeile']]],
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'Triggers Demo',
    );

    Livewire::test('cms::collection-entries-list', [
        'collectionKey' => $elementCollection->collection_key,
        'elementCollection' => true,
    ])
        ->assertSet('collectionId', $elementCollection->id);
});

it('handles null and non-existent collection keys gracefully', function (mixed $input): void {
    Livewire::test('cms::collection-entries-list', ['collectionKey' => $input])
        ->assertSet('collectionKey', null)
        ->assertSee(__('Please select a collection from the navigation.'))
        ->assertStatus(200);
})->with(['null' => null, 'non-existent id' => 999999]);

it('resolves the existing collection row via the definition key instead of creating a hyphenated duplicate', function (): void {
    $real = Collection::create([
        'tenant_id' => $this->tenant->id,
        'collection_key' => 'STELLENANGEBOTE_PERMANENT',
        'name' => 'Stellenangebote Permanent',
        'is_element_collection' => false,
    ]);

    CollectionDefinition::create([
        'tenant_id' => $this->tenant->id,
        'filename' => 'stellenangebote-permanent',
        'key' => 'STELLENANGEBOTE_PERMANENT',
        'title' => 'Stellenangebote Permanent',
        'title_list' => 'Stellenangebote Permanent',
        'has_page' => true,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Name', 'colspan' => 6],
        ],
    ]);

    $this->get(route('cms.collections') . '?key=stellenangebote-permanent')->assertOk();

    expect(Collection::query()->where('collection_key', 'STELLENANGEBOTE-PERMANENT')->exists())->toBeFalse()
        ->and(Collection::query()->where('collection_key', 'STELLENANGEBOTE_PERMANENT')->count())->toBe(1)
        ->and(Collection::find($real->id))->not->toBeNull();
});

it('still creates a collection row from the uppercased key when no definition exists', function (): void {
    $this->get(route('cms.collections') . '?key=adhoc-things')->assertOk();

    expect(Collection::query()->where('collection_key', 'ADHOC-THINGS')->count())->toBe(1);
});

it('renders dynamic columns and translated values from the definition fields', function (): void {
    useGermanOnly($this->tenant->id);
    $collection = entriesListDefinition($this->tenant->id, 'projects', [
        ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ['name' => 'detailData.image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
    ], hasPage: true);

    Page::create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => ['name' => ['de' => 'Laravel Projekt', 'en' => 'Laravel Project'], 'image' => '/storage/test-image.jpg'],
        'sort' => 1,
    ]);
    Page::create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => ['name' => ['de' => 'Vue.js Anwendung', 'en' => 'Vue.js Application']],
        'sort' => 2,
    ]);

    $response = $this->get('/cms/collections?key=projects');
    $response->assertOk();
    $response->assertSee('Name');
    $response->assertSee('Image');
    $response->assertSee('Sortierung');
    $response->assertSee('Laravel Projekt');
    $response->assertSee('Vue.js Anwendung');
    $response->assertSee('✓ Bild vorhanden');
});

it('handles empty collection entries gracefully', function (): void {
    useGermanOnly($this->tenant->id);
    entriesListDefinition($this->tenant->id, 'projects', [
        ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ['name' => 'detailData.image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
    ], hasPage: true);

    $response = $this->get('/cms/collections?key=projects');
    $response->assertOk();
    $response->assertSee('Name');
    $response->assertSee('Image');
});

it('renders element-collection fields in the list with their row count', function (): void {
    useGermanOnly($this->tenant->id);
    $collection = entriesListDefinition($this->tenant->id, 'services', [
        ['name' => 'detailData.title', 'label' => 'Titel', 'type' => 'translatableText', 'colspan' => 6],
        [
            'name' => 'detailData.activities_items',
            'label' => 'Activities',
            'type' => 'element-collection',
            'fields' => [
                ['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea'],
            ],
        ],
    ], hasPage: true);

    $entry = Page::create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => ['title' => ['de' => 'Beratung', 'en' => 'Consulting']],
        'sort' => 1,
    ]);

    $elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $entry->id,
        $this->tenant->id,
        'activities_items',
        [['text' => ['de' => 'Analyse']], ['text' => ['de' => 'Konzeption']]],
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'Activities Beratung',
    );

    expect($elementCollection->rows()->count())->toBe(2);

    Livewire::test('cms::collection-entries-list', ['collectionKey' => 'services'])
        ->assertSee('Beratung')
        ->assertSee('2 ' . trans_choice('Eintrag|Einträge', 2));
});

it('shows the linked page name for pageRelation fields and marks the column as badge', function (): void {
    $collection = entriesListDefinition($this->tenant->id, 'beratung', [
        ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'colspan' => 6],
        ['name' => 'page_id', 'label' => 'Verlinkte Seite', 'type' => 'pageRelation', 'colspan' => 6],
    ]);

    $linkedPage = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Kontaktseite'],
    ]);
    Page::create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => ['title' => 'Erster Eintrag', 'page_id' => $linkedPage->id],
        'sort' => 0,
        'is_active' => true,
    ]);

    $component = Livewire::test('cms::collection-entries-list', ['collectionKey' => 'beratung'])
        ->assertSee('Kontaktseite');

    $listConfig = $component->instance()->listData();
    $row = $listConfig['rows']->first();
    $columns = collect($listConfig['listSettings']['columns']);

    expect($row['page_id'])->toBe('Kontaktseite')
        ->and($columns->firstWhere('field', 'page_id')['type'] ?? null)->toBe('badge')
        ->and($columns->firstWhere('field', 'title')['type'] ?? null)->not->toBe('badge');
});

it('renders an empty cell when the linked page no longer exists', function (): void {
    $collection = entriesListDefinition($this->tenant->id, 'beratung', [
        ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'colspan' => 6],
        ['name' => 'page_id', 'label' => 'Verlinkte Seite', 'type' => 'pageRelation', 'colspan' => 6],
    ]);

    Page::create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => ['title' => 'Verwaister Eintrag', 'page_id' => 999999],
        'sort' => 0,
        'is_active' => true,
    ]);

    $component = Livewire::test('cms::collection-entries-list', ['collectionKey' => 'beratung'])
        ->assertHasNoErrors()
        ->assertDontSee('999999');

    expect($component->instance()->listData()['rows']->first()['page_id'])->toBe('');
});

it('lists element collection rows and hides the manage-collection action', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);

    $elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'Mein Trigger DE', 'en' => 'My Trigger EN']]],
        [['name' => 'text', 'label' => 'Text', 'type' => 'translatableTextarea', 'colspan' => 12]],
        'Triggers Demo',
    );

    Livewire::test('cms::collection-entries-list', [
        'collectionKey' => $elementCollection->collection_key,
        'elementCollection' => true,
    ])
        ->assertSet('elementCollection', true)
        ->assertSee('My Trigger EN')
        ->assertDontSee('Manage Collection');
});

it('opens the dedicated element-collection row editor (not page-detail) from the list', function (): void {
    $owner = Page::factory()->create(['tenant_id' => $this->tenant->id, 'name' => ['de' => 'Demo']]);

    $elementCollection = app(ElementCollectionService::class)->importItems(
        ElementCollectionService::OWNER_PAGE,
        $owner->id,
        $owner->tenant_id,
        'triggers_items',
        [['text' => ['de' => 'Zeile', 'en' => 'Row']]],
        [['name' => 'text', 'type' => 'translatableTextarea']],
        'Triggers Demo',
    );

    Livewire::test('cms::collection-entries-list', [
        'collectionKey' => $elementCollection->collection_key,
        'elementCollection' => true,
    ])
        ->call('listAction', $elementCollection->rows()->first()->id)
        ->assertDispatched('noerdModal', modalComponent: 'cms::element-collection-row-detail');
});

it('displays translatable values in the selected filter language and persists it to the session', function (): void {
    CmsLanguage::where('tenant_id', $this->tenant->id)->delete();
    CmsLanguage::create(['tenant_id' => $this->tenant->id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true, 'is_active' => true]);
    CmsLanguage::create(['tenant_id' => $this->tenant->id, 'code' => 'en', 'name' => 'English', 'is_default' => false, 'is_active' => true]);

    $collection = entriesListDefinition($this->tenant->id, 'projects', [
        ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
    ], hasPage: true);

    Page::create([
        'tenant_id' => $this->tenant->id,
        'collection_id' => $collection->id,
        'data' => ['name' => ['de' => 'Deutscher Titel', 'en' => 'English Title']],
        'sort' => 1,
    ]);

    Livewire::test('cms::collection-entries-list', ['collectionKey' => 'projects'])
        ->assertSee('Deutscher Titel')
        ->assertDontSee('English Title');

    session(['listFilters' => ['language' => 'en']]);

    Livewire::test('cms::collection-entries-list', ['collectionKey' => 'projects'])
        ->assertSee('English Title')
        ->call('storeActiveListFilters');

    expect(session('selectedLanguage'))->toBe('en');
});
