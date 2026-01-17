<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'page-detail',
    'listName' => 'pages-list',
    'id' => 'pageId',
];

it('test the route', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    $response = $this->get(route('cms.pages'));
    $response->assertStatus(200);
});

it('validates the data', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    // Test with invalid data (empty name array)
    Volt::test($testSettings['componentName'])
        ->set('pageData.name', [])
        ->call('store')
        ->assertHasErrors(['pageData.name']);
});

it('successfully stores the data', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName'])
        ->set('pageData.name.de', 'Test Seite')
        ->set('pageData.name.en', 'Test Page')
        ->set('pageData.layout', 'weblayout')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $user->selected_tenant_id,
        'name' => '{"de":"Test Seite","en":"Test Page"}',
        'layout' => 'weblayout',
    ]);
});

it('successfully deletes a page', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['de' => 'Test Seite', 'en' => 'Test Page'],
        'slug' => ['de' => '/test-seite', 'en' => '/test-page'],
    ]);

    Volt::test($testSettings['componentName'], ['pageId' => $model->id])
        ->call('delete')
        ->assertDispatched('reloadTable-' . $testSettings['listName']);

    $this->assertDatabaseMissing('pages', [
        'id' => $model->id,
    ]);
});

it('opens page with pageId', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['de' => 'Test Seite', 'en' => 'Test Page'],
        'slug' => ['de' => '/test-seite', 'en' => '/test-page'],
    ]);

    $component = Volt::test($testSettings['componentName'], ['pageId' => $model->id]);

    $component->assertSet('pageId', $model->id);
    // Note: model.id might be set differently due to FieldHelper parsing
});

it('opens and stores existing page', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['de' => 'Alte Seite', 'en' => 'Old Page'],
        'slug' => ['de' => '/alte-seite', 'en' => '/old-page'],
    ]);

    Volt::test($testSettings['componentName'], ['pageId' => $model->id])
        ->set('pageData.name.de', 'Neue Seite')
        ->set('pageData.name.en', 'New Page')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'id' => $model->id,
        'name' => '{"de":"Neue Seite","en":"New Page"}',
    ]);
});

it('dispatches table action from pages table', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    // Test just the tableAction method without rendering the full table
    $component = Volt::test($testSettings['listName']);

    $component->call('tableAction', 123)
        ->assertDispatched(
            'noerdModal',
            modalComponent: $testSettings['componentName'],
            source: $testSettings['listName'],
            arguments: ['pageId' => 123, 'relationId' => null],
        );
});

it('sets a table key for the list', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    Volt::test($testSettings['listName'])
        ->assertNotSet('tableId', '');
});
