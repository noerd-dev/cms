<?php

use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Livewire\Volt\Volt;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'page-detail',
    'listName' => 'pages-list',
    'id' => 'modelId',
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
        ->set('model.name', [])
        ->call('store')
        ->assertHasErrors(['model.name']);
});

it('successfully stores the data', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName'])
        ->set('model.name.de', 'Test Seite')
        ->set('model.name.en', 'Test Page')
        ->set('model.layout', 'weblayout')
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
        'name' => '{"de":"Test Seite","en":"Test Page"}',
        'slug' => '{"de":"/test-seite","en":"/test-page"}',
    ]);

    Volt::test($testSettings['componentName'], ['modelId' => $model->id])
        ->call('delete')
        ->assertDispatched('reloadTable-' . $testSettings['listName']);

    $this->assertDatabaseMissing('pages', [
        'id' => $model->id,
    ]);
});

it('opens page with modelId', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => '{"de":"Test Seite","en":"Test Page"}',
        'slug' => '{"de":"/test-seite","en":"/test-page"}',
    ]);

    $component = Volt::test($testSettings['componentName'], ['modelId' => $model->id]);

    $component->assertSet('modelId', $model->id);
    // Note: model.id might be set differently due to FieldHelper parsing
});

it('opens and stores existing page', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => '{"de":"Alte Seite","en":"Old Page"}',
        'slug' => '{"de":"/alte-seite","en":"/old-page"}',
    ]);

    Volt::test($testSettings['componentName'], ['modelId' => $model->id])
        ->set('model.name.de', 'Neue Seite')
        ->set('model.name.en', 'New Page')
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
            component: $testSettings['componentName'],
            source: $testSettings['listName'],
            arguments: ['modelId' => 123, 'relationId' => null],
        );
});

it('sets a table key for the list', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    Volt::test($testSettings['listName'])
        ->assertNotSet('tableId', '');
});

it('shows a preview error when element component is missing', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create a page
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    // Create element_page with a missing element_key
    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => '____missing____',
        'data' => json_encode(['foo' => 'bar']),
        'sort' => 1,
    ]);

    Volt::test($testSettings['componentName'], ['modelId' => $page->id])
        ->set('viewMode', 'preview')
        ->assertSee('Element-Komponente nicht gefunden:')
        ->assertSee('Please create both the .yml and .blade.php files in the elements folder.')
        ->assertSee('____missing____');
});
