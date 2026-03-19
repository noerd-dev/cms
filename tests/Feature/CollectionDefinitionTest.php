<?php

use Livewire\Livewire;
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
        'buttonList' => 'cms_new_contact',
        'description' => '',
        'hasPage' => true,
        'fields' => [
            ['name' => 'model.name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ],
    ]));
}

afterEach(function (): void {
    // Clean up test-created YAML files
    foreach (['test-definition', 'test-definition-2', 'test-store', 'test-duplicate', 'film', 'my-collection', 'contacts'] as $name) {
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

it('sets filename field to readonly when editing', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    createContactsFixture();

    $component = Livewire::test('collection-definition-detail', ['modelId' => 'contacts']);

    $pageLayout = $component->get('pageLayout');
    $filenameField = collect($pageLayout['fields'])->firstWhere('name', 'detailData.filename');
    expect($filenameField['readonly'])->toBeTrue();
});

it('does not set filename field to readonly when creating', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $component = Livewire::test('collection-definition-detail');

    $pageLayout = $component->get('pageLayout');
    $filenameField = collect($pageLayout['fields'])->firstWhere('name', 'detailData.filename');
    expect($filenameField['readonly'] ?? false)->toBeFalse();
});

it('creates a new YAML file with correct structure', function (): void {
    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test('collection-definition-detail')
        ->set('detailData.filename', 'test-store')
        ->set('detailData.title', 'Test Store')
        ->set('detailData.titleList', 'Test Stores')
        ->set('detailData.buttonList', 'New Test')
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
