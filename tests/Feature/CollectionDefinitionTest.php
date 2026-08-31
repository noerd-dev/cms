<?php

use Livewire\Livewire;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    DatabaseCollectionDefinitionRepository::resetCache();
    app()->forgetInstance(CollectionHelper::class);
});

/**
 * Create a "contacts" collection definition in the database for the given tenant.
 */
function createContactsDefinition(int $tenantId): CollectionDefinition
{
    return CollectionDefinition::create([
        'tenant_id' => $tenantId,
        'filename' => 'contacts',
        'key' => 'CONTACTS',
        'title' => 'Kontakt',
        'title_list' => 'Kontakte',
        'description' => '',
        'has_page' => true,
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ],
    ]);
}

it('renders the list component and shows existing definitions', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    $response = $this->get('/cms/collection-definitions');
    $response->assertStatus(200);

    Livewire::test('cms::collection-definitions-list')
        ->assertNotSet('listId', '');
});

it('scopes entry counts to the current tenant', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);
    $ownCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'CONTACTS',
        'name' => 'Kontakte',
    ]);
    Page::factory()->create(['tenant_id' => $tenant->id, 'collection_id' => $ownCollection->id]);

    $otherTenant = Tenant::factory()->create();
    $foreignCollection = Collection::create([
        'tenant_id' => $otherTenant->id,
        'collection_key' => 'CONTACTS',
        'name' => 'Kontakte',
    ]);
    Page::factory()->count(3)->create(['tenant_id' => $otherTenant->id, 'collection_id' => $foreignCollection->id]);

    $component = Livewire::test('cms::collection-definitions-list');
    $row = collect($component->viewData('listConfig')['rows']->items())->firstWhere('key', 'CONTACTS');

    expect($row['entryCount'])->toBe(1);
});

it('searches definitions by titleList, key, and filename case-insensitively', function (string $term): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);
    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'sliders',
        'key' => 'SLIDERS',
        'title' => 'Slider',
        'title_list' => 'Sliders',
        'has_page' => false,
        'fields' => [],
    ]);

    $component = Livewire::test('cms::collection-definitions-list')
        ->set('search', $term);

    $rows = collect($component->viewData('listConfig')['rows']->items());

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['key'])->toBe('CONTACTS');
})->with([
    'titleList' => 'kontakte',
    'key' => 'conta',
    'filename' => 'CONTACTS',
]);

it('filters definitions by has_page', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);
    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'sliders',
        'key' => 'SLIDERS',
        'title' => 'Slider',
        'title_list' => 'Sliders',
        'has_page' => false,
        'fields' => [],
    ]);

    $pageRows = collect(Livewire::test('cms::collection-definitions-list')
        ->set('listFilters.has_page', 'page')
        ->viewData('listConfig')['rows']->items());

    $dataRows = collect(Livewire::test('cms::collection-definitions-list')
        ->set('listFilters.has_page', 'data')
        ->viewData('listConfig')['rows']->items());

    expect($pageRows->pluck('key')->all())->toBe(['CONTACTS'])
        ->and($dataRows->pluck('key')->all())->toBe(['SLIDERS']);
});

it('dispatches modal when listAction is called', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);

    Livewire::test('cms::collection-definitions-list')
        ->call('listAction', 'contacts')
        ->assertDispatched(
            'noerdModal',
            fn (string $event, array $params): bool => ($params['route'] ?? null) === 'cms.collection-definition.detail'
                && ($params['arguments']['modelId'] ?? null) === 'contacts',
        );
});

it('loads existing collection definition in detail component', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'contacts'])
        ->assertSet('isEditing', true)
        ->assertSet('detailData.filename', 'contacts')
        ->assertSet('detailData.title', 'Kontakt');
});

it('allows renaming the filename of an existing collection definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'contacts'])
        ->set('detailData.filename', 'contacts-renamed')
        ->call('store')
        ->assertHasNoErrors();

    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts-renamed')->exists())->toBeTrue();
    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts')->exists())->toBeFalse();
    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts-renamed')->first()->key)->toBe('CONTACTS_RENAMED');
});

it('prevents renaming to an existing filename', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);

    // Create a second definition that we'll try to rename to
    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'contacts-renamed',
        'key' => 'CONTACTS_RENAMED',
        'title' => 'Existing',
        'title_list' => 'Existing',
        'has_page' => false,
        'fields' => [],
    ]);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'contacts'])
        ->set('detailData.filename', 'contacts-renamed')
        ->call('store')
        ->assertHasErrors('detailData.filename');

    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts')->exists())->toBeTrue();
});

it('creates a new collection definition with correct structure', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    Livewire::test('cms::collection-definition-detail')
        ->set('detailData.filename', 'test-store')
        ->set('detailData.title', 'Test Store')
        ->set('detailData.titleList', 'Test Stores')
        ->set('detailData.hasPage', true)
        ->call('store')
        ->assertHasNoErrors();

    $definition = CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'test-store')->first();
    expect($definition)->not->toBeNull();
    expect($definition->title)->toBe('Test Store');
    expect($definition->title_list)->toBe('Test Stores');
    expect($definition->key)->toBe('TEST_STORE');
    expect($definition->has_page)->toBeTrue();
});

it('prevents duplicate filenames', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'test-duplicate',
        'key' => 'TEST_DUPLICATE',
        'title' => 'Existing',
        'title_list' => 'Existing',
        'has_page' => false,
        'fields' => [],
    ]);

    Livewire::test('cms::collection-definition-detail')
        ->set('detailData.filename', 'test-duplicate')
        ->set('detailData.title', 'Duplicate')
        ->set('detailData.titleList', 'Duplicates')
        ->call('store')
        ->assertHasErrors('detailData.filename');
});

it('validates required fields', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    Livewire::test('cms::collection-definition-detail')
        ->set('detailData.filename', '')
        ->set('detailData.title', '')
        ->set('detailData.titleList', '')
        ->call('store')
        ->assertHasErrors([
            'detailData.filename',
            'detailData.title',
            'detailData.titleList',
        ]);
});

it('validates filename format', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    Livewire::test('cms::collection-definition-detail')
        ->set('detailData.filename', 'Invalid Name!')
        ->set('detailData.title', 'Test')
        ->set('detailData.titleList', 'Tests')
        ->call('store')
        ->assertHasErrors('detailData.filename');
});

it('normalizes filename by lowercasing', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    Livewire::test('cms::collection-definition-detail')
        ->set('detailData.filename', 'FILM')
        ->set('detailData.title', 'Film')
        ->set('detailData.titleList', 'Films')
        ->call('store')
        ->assertHasNoErrors();

    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'film')->exists())->toBeTrue();
});

it('normalizes underscores to hyphens in filename', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    Livewire::test('cms::collection-definition-detail')
        ->set('detailData.filename', 'My_Collection')
        ->set('detailData.title', 'My Collection')
        ->set('detailData.titleList', 'My Collections')
        ->call('store')
        ->assertHasNoErrors();

    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'my-collection')->exists())->toBeTrue();
});

it('adds and removes fields', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    Livewire::test('cms::collection-definition-detail')
        ->assertSet('fields', [])
        ->call('addField')
        ->assertCount('fields', 1)
        ->call('addField')
        ->assertCount('fields', 2)
        ->call('removeField', 0)
        ->assertCount('fields', 1);
});

it('stores fields in the collection definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    Livewire::test('cms::collection-definition-detail')
        ->set('detailData.filename', 'test-definition')
        ->set('detailData.title', 'Test Def')
        ->set('detailData.titleList', 'Test Defs')
        ->call('addField')
        ->set('fields.0.name', 'my_field')
        ->set('fields.0.label', 'My Field')
        ->set('fields.0.type', 'translatableText')
        ->set('fields.0.colspan', 6)
        ->call('store')
        ->assertHasNoErrors();

    $definition = CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'test-definition')->first();
    expect($definition->fields)->toHaveCount(1);
    expect($definition->fields[0]['name'])->toBe('my_field');
    expect($definition->fields[0]['type'])->toBe('translatableText');
});

it('copies a collection definition with key, title and titleList all suffixed with 2', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'contacts'])
        ->call('copy')
        ->assertHasNoErrors();

    $copy = CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'contacts2')->first();
    expect($copy)->not->toBeNull();
    expect($copy->key)->toBe('CONTACTS2');
    expect($copy->title)->toBe('Kontakt2');
    expect($copy->title_list)->toBe('Kontakte2');
    expect($copy->fields)->toHaveCount(1);

    // Mirrors into the collections instance table with the current user as creator.
    $instance = Collection::where('tenant_id', $tenant->id)->where('collection_key', 'CONTACTS2')->first();
    expect($instance)->not->toBeNull();
    expect($instance->created_by)->toBe($user->id);
    expect($instance->name)->toBe('Kontakte2');
});

it('prevents copying when target definition already exists', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    createContactsDefinition($tenant->id);

    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'contacts2',
        'key' => 'CONTACTS2',
        'title' => 'Existing',
        'title_list' => 'Existing',
        'has_page' => false,
        'fields' => [],
    ]);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'contacts'])
        ->call('copy')
        ->assertHasErrors('detailData.filename');
});

it('deletes a collection definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    $definition = CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'test-definition-2',
        'key' => 'TEST_DEFINITION_2',
        'title' => 'To Delete',
        'title_list' => 'To Delete',
        'has_page' => false,
        'fields' => [],
    ]);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'test-definition-2'])
        ->call('delete');

    expect(CollectionDefinition::find($definition->id))->toBeNull();
});

it('deletes associated collection records when deleting a collection definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'test-definition-2',
        'key' => 'TEST_DEFINITION_2',
        'title' => 'To Delete',
        'title_list' => 'To Delete',
        'has_page' => false,
        'fields' => [],
    ]);

    $collection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'TEST_DEFINITION_2',
        'name' => 'To Delete',
    ]);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'collection_id' => $collection->id,
    ]);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'test-definition-2'])
        ->call('delete');

    expect(CollectionDefinition::where('tenant_id', $tenant->id)->where('filename', 'test-definition-2')->exists())->toBeFalse();
    expect(Collection::find($collection->id))->toBeNull();
    expect(Page::find($page->id))->toBeNull();
});

it('shows rename confirmation when a field name is changed', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'rename-test',
        'key' => 'RENAME_TEST',
        'title' => 'Rename Test',
        'title_list' => 'Rename Tests',
        'has_page' => false,
        'fields' => [
            ['name' => 'headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test'])
        ->set('fields.0.name', 'headline-one')
        ->call('store')
        ->assertSet('showRenameConfirmation', true)
        ->assertSet('pendingRenames', ['headline1' => 'headline-one']);
});

it('renames field keys in database entries when confirmed', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'rename-test',
        'key' => 'RENAME_TEST',
        'title' => 'Rename Test',
        'title_list' => 'Rename Tests',
        'has_page' => false,
        'fields' => [
            ['name' => 'headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    $collection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'RENAME_TEST',
        'name' => 'Rename Test',
    ]);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'collection_id' => $collection->id,
        'data' => ['headline1' => 'Hello World', 'other' => 'unchanged'],
    ]);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test'])
        ->set('fields.0.name', 'headline-one')
        ->call('store')
        ->assertSet('showRenameConfirmation', true)
        ->call('confirmRenameAndSave')
        ->assertSet('showRenameConfirmation', false);

    $page->refresh();
    expect($page->data)->toHaveKey('headline-one', 'Hello World');
    expect($page->data)->not->toHaveKey('headline1');
    expect($page->data)->toHaveKey('other', 'unchanged');
});

it('skips database rename when user declines', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user, NoerdAuth::guardName());

    CollectionDefinition::create([
        'tenant_id' => $tenant->id,
        'filename' => 'rename-test',
        'key' => 'RENAME_TEST',
        'title' => 'Rename Test',
        'title_list' => 'Rename Tests',
        'has_page' => false,
        'fields' => [
            ['name' => 'headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]);

    $collection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'RENAME_TEST',
        'name' => 'Rename Test',
    ]);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'collection_id' => $collection->id,
        'data' => ['headline1' => 'Hello World'],
    ]);

    Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test'])
        ->set('fields.0.name', 'headline-one')
        ->call('store')
        ->assertSet('showRenameConfirmation', true)
        ->call('skipRenameAndSave')
        ->assertSet('showRenameConfirmation', false);

    $page->refresh();
    expect($page->data)->toHaveKey('headline1', 'Hello World');
    expect($page->data)->not->toHaveKey('headline-one');
});
