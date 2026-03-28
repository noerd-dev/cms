<?php


use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'page-detail',
    'listName' => 'pages-list',
    'id' => 'modelId',
    'urlParam' => 'pageId',
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
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', [])
        ->call('store')
        ->assertHasErrors(['detailData.name']);
});

it('successfully stores the data', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    $component = Livewire::test($testSettings['componentName'])
        ->set('detailData.name.en', 'Test Page')
        ->set('detailData.name.de', 'Test Seite')
        ->set('detailData.layout', 'weblayout')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $user->selected_tenant_id,
        'name' => '{"en":"Test Page","de":"Test Seite"}',
        'layout' => 'weblayout',
    ]);
});

it('successfully deletes a page', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['en' => 'Test Page', 'de' => 'Test Seite'],
        'slug' => ['en' => '/test-page', 'de' => '/test-seite'],
    ]);

    Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->call('delete')
        ->assertDispatched('closeTopModal');

    $this->assertDatabaseMissing('pages', [
        'id' => $model->id,
    ]);
});

it('opens page with pageId', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['en' => 'Test Page', 'de' => 'Test Seite'],
        'slug' => ['en' => '/test-page', 'de' => '/test-seite'],
    ]);

    $component = Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName']);

    $component->assertSet('modelId', $model->id);
    // Note: model.id might be set differently due to FieldHelper parsing
});

it('opens and stores existing page', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['en' => 'Old Page', 'de' => 'Alte Seite'],
        'slug' => ['en' => '/old-page', 'de' => '/alte-seite'],
    ]);

    Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->set('detailData.name.en', 'New Page')
        ->set('detailData.name.de', 'Neue Seite')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'id' => $model->id,
        'name' => '{"en":"New Page","de":"Neue Seite"}',
    ]);
});

it('dispatches table action from pages table', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    // Test just the listAction method without rendering the full table
    $component = Livewire::test($testSettings['listName']);

    $component->call('listAction', 123)
        ->assertDispatched(
            'noerdModal',
            modalComponent: $testSettings['componentName'],
            source: $testSettings['listName'],
            arguments: ['modelId' => 123, 'relations' => []],
        );
});

it('sets a table key for the list', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    Livewire::test($testSettings['listName'])
        ->assertNotSet('listId', '');
});
