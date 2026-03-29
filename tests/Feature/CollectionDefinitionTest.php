<?php

use Livewire\Livewire;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

function collectionsPath(): string
{
    return base_path('app-configs/cms/collections');
}

function createContactsFixture(): void
{
    $path = collectionsPath() . '/contacts.yml';
    file_put_contents($path, Yaml::dump([
        'title' => 'Kontakt',
        'titleList' => 'Kontakte',
        'key' => 'CONTACTS',
        'description' => '',
        'hasPage' => true,
        'fields' => [
            ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ],
    ]));
}

afterEach(function (): void {
    // Clean up test-created YAML files
    foreach (['test-definition', 'test-definition-2', 'test-store', 'test-duplicate', 'film', 'my-collection', 'contacts', 'contacts2', 'contacts-renamed', 'rename-test'] as $name) {
        $path = collectionsPath() . '/' . $name . '.yml';
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('renders the list component and shows existing YAML files', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/collection-definitions');
    $response->assertStatus(200);

    Livewire::test('collection-definitions-list')
        ->assertNotSet('listId', '');
});

it('dispatches modal when listAction is called', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    createContactsFixture();

    Livewire::test('collection-definitions-list')
        ->call('listAction', 'contacts')
        ->assertDispatched('noerdModal', modalComponent: 'collection-definition-detail');
});

it('loads existing collection definition in detail component', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    createContactsFixture();

    Livewire::test('collection-definition-detail', ['modelId' => 'contacts'])
        ->assertSet('isEditing', true)
        ->assertSet('detailData.filename', 'contacts')
        ->assertSet('detailData.title', 'Kontakt');
});

it('loads pageLayout with metadata fields from YAML config', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $component = Livewire::test('collection-definition-detail');

    $pageLayout = $component->get('pageLayout');
    expect($pageLayout)->not->toBeEmpty();
    expect($pageLayout['fields'])->toBeArray();

    $fieldNames = array_column($pageLayout['fields'], 'name');
    expect($fieldNames)->toContain('detailData.filename');
    expect($fieldNames)->toContain('detailData.title');
    expect($fieldNames)->toContain('detailData.titleList');
    expect($fieldNames)->toContain('detailData.hasPage');
});

it('allows renaming the filename of an existing collection definition', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    createContactsFixture();

    Livewire::test('collection-definition-detail', ['modelId' => 'contacts'])
        ->set('detailData.filename', 'contacts-renamed')
        ->call('store')
        ->assertHasNoErrors();

    expect(file_exists(collectionsPath() . '/contacts-renamed.yml'))->toBeTrue();
    expect(file_exists(collectionsPath() . '/contacts.yml'))->toBeFalse();

    $content = Yaml::parseFile(collectionsPath() . '/contacts-renamed.yml');
    expect($content['key'])->toBe('CONTACTS_RENAMED');
});

it('prevents renaming to an existing filename', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    createContactsFixture();

    // Create a second file that we'll try to rename to
    file_put_contents(collectionsPath() . '/contacts-renamed.yml', Yaml::dump([
        'title' => 'Existing',
        'titleList' => 'Existing',
        'key' => 'CONTACTS_RENAMED',
        'fields' => [],
    ]));

    Livewire::test('collection-definition-detail', ['modelId' => 'contacts'])
        ->set('detailData.filename', 'contacts-renamed')
        ->call('store')
        ->assertHasErrors('detailData.filename');

    // Original file should still exist
    expect(file_exists(collectionsPath() . '/contacts.yml'))->toBeTrue();
});

it('creates a new YAML file with correct structure', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('collection-definition-detail')
        ->set('detailData.filename', 'test-store')
        ->set('detailData.title', 'Test Store')
        ->set('detailData.titleList', 'Test Stores')
        ->set('detailData.hasPage', true)
        ->call('store')
        ->assertHasNoErrors();

    $path = collectionsPath() . '/test-store.yml';
    expect(file_exists($path))->toBeTrue();

    $content = Yaml::parseFile($path);
    expect($content['title'])->toBe('Test Store');
    expect($content['titleList'])->toBe('Test Stores');
    expect($content['key'])->toBe('TEST_STORE');
    expect($content['hasPage'])->toBeTrue();
});

it('prevents duplicate filenames', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create an initial file
    file_put_contents(collectionsPath() . '/test-duplicate.yml', Yaml::dump([
        'title' => 'Existing',
        'titleList' => 'Existing',
        'key' => 'TEST_DUPLICATE',
        'fields' => [],
    ]));

    Livewire::test('collection-definition-detail')
        ->set('detailData.filename', 'test-duplicate')
        ->set('detailData.title', 'Duplicate')
        ->set('detailData.titleList', 'Duplicates')
        ->call('store')
        ->assertHasErrors('detailData.filename');
});

it('validates required fields', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('collection-definition-detail')
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
    $this->actingAs($user);

    Livewire::test('collection-definition-detail')
        ->set('detailData.filename', 'Invalid Name!')
        ->set('detailData.title', 'Test')
        ->set('detailData.titleList', 'Tests')
        ->call('store')
        ->assertHasErrors('detailData.filename');
});

it('normalizes filename by lowercasing, stripping yml extension, and replacing underscores', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('collection-definition-detail')
        ->set('detailData.filename', 'FILM.YML')
        ->set('detailData.title', 'Film')
        ->set('detailData.titleList', 'Films')
        ->call('store')
        ->assertHasNoErrors();

    expect(file_exists(collectionsPath() . '/film.yml'))->toBeTrue();
});

it('normalizes underscores to hyphens in filename', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('collection-definition-detail')
        ->set('detailData.filename', 'My_Collection')
        ->set('detailData.title', 'My Collection')
        ->set('detailData.titleList', 'My Collections')
        ->call('store')
        ->assertHasNoErrors();

    expect(file_exists(collectionsPath() . '/my-collection.yml'))->toBeTrue();
});

it('adds and removes fields', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('collection-definition-detail')
        ->assertSet('fields', [])
        ->call('addField')
        ->assertCount('fields', 1)
        ->call('addField')
        ->assertCount('fields', 2)
        ->call('removeField', 0)
        ->assertCount('fields', 1);
});

it('stores fields in YAML file', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('collection-definition-detail')
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

    $content = Yaml::parseFile(collectionsPath() . '/test-definition.yml');
    expect($content['fields'])->toHaveCount(1);
    expect($content['fields'][0]['name'])->toBe('detailData.my_field');
    expect($content['fields'][0]['type'])->toBe('translatableText');
});

it('copies a collection definition with key suffix 2', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    createContactsFixture();

    Livewire::test('collection-definition-detail', ['modelId' => 'contacts'])
        ->call('copy')
        ->assertHasNoErrors();

    $copiedPath = collectionsPath() . '/contacts2.yml';
    expect(file_exists($copiedPath))->toBeTrue();

    $content = Yaml::parseFile($copiedPath);
    expect($content['key'])->toBe('CONTACTS2');
    expect($content['title'])->toBe('Kontakt');
    expect($content['titleList'])->toBe('Kontakte');
    expect($content['fields'])->toHaveCount(1);
});

it('prevents copying when target file already exists', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    createContactsFixture();

    // Create the target file so copy should fail
    file_put_contents(collectionsPath() . '/contacts2.yml', Yaml::dump([
        'title' => 'Existing',
        'titleList' => 'Existing',
        'key' => 'CONTACTS2',
        'fields' => [],
    ]));

    Livewire::test('collection-definition-detail', ['modelId' => 'contacts'])
        ->call('copy')
        ->assertHasErrors('detailData.filename');
});

it('deletes a YAML file', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create a test file
    $path = collectionsPath() . '/test-definition-2.yml';
    file_put_contents($path, Yaml::dump([
        'title' => 'To Delete',
        'titleList' => 'To Delete',
        'key' => 'TEST_DEFINITION_2',
        'fields' => [],
    ]));

    expect(file_exists($path))->toBeTrue();

    Livewire::test('collection-definition-detail', ['modelId' => 'test-definition-2'])
        ->call('delete');

    expect(file_exists($path))->toBeFalse();
});

it('deletes associated database records when deleting a collection definition', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $path = collectionsPath() . '/test-definition-2.yml';
    file_put_contents($path, Yaml::dump([
        'title' => 'To Delete',
        'titleList' => 'To Delete',
        'key' => 'TEST_DEFINITION_2',
        'fields' => [],
    ]));

    $collection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'TEST_DEFINITION_2',
        'name' => 'To Delete',
    ]);

    $page = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'collection_id' => $collection->id,
    ]);

    Livewire::test('collection-definition-detail', ['modelId' => 'test-definition-2'])
        ->call('delete');

    expect(file_exists($path))->toBeFalse();
    expect(Collection::find($collection->id))->toBeNull();
    expect(Page::find($page->id))->toBeNull();
});

it('shows rename confirmation when a field name is changed', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $path = collectionsPath() . '/rename-test.yml';
    file_put_contents($path, Yaml::dump([
        'title' => 'Rename Test',
        'titleList' => 'Rename Tests',
        'key' => 'RENAME_TEST',
        'description' => '',
        'hasPage' => false,
        'fields' => [
            ['name' => 'detailData.headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]));

    Livewire::test('collection-definition-detail', ['modelId' => 'rename-test'])
        ->set('fields.0.name', 'headline-one')
        ->call('store')
        ->assertSet('showRenameConfirmation', true)
        ->assertSet('pendingRenames', ['headline1' => 'headline-one']);
});

it('renames field keys in database entries when confirmed', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $path = collectionsPath() . '/rename-test.yml';
    file_put_contents($path, Yaml::dump([
        'title' => 'Rename Test',
        'titleList' => 'Rename Tests',
        'key' => 'RENAME_TEST',
        'description' => '',
        'hasPage' => false,
        'fields' => [
            ['name' => 'detailData.headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]));

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

    Livewire::test('collection-definition-detail', ['modelId' => 'rename-test'])
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
    $this->actingAs($user);

    $path = collectionsPath() . '/rename-test.yml';
    file_put_contents($path, Yaml::dump([
        'title' => 'Rename Test',
        'titleList' => 'Rename Tests',
        'key' => 'RENAME_TEST',
        'description' => '',
        'hasPage' => false,
        'fields' => [
            ['name' => 'detailData.headline1', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
        ],
    ]));

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

    Livewire::test('collection-definition-detail', ['modelId' => 'rename-test'])
        ->set('fields.0.name', 'headline-one')
        ->call('store')
        ->assertSet('showRenameConfirmation', true)
        ->call('skipRenameAndSave')
        ->assertSet('showRenameConfirmation', false);

    $page->refresh();
    expect($page->data)->toHaveKey('headline1', 'Hello World');
    expect($page->data)->not->toHaveKey('headline-one');
});
