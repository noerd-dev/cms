<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'cms::page-detail',
    'listName' => 'cms::pages-list',
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
            fn(string $event, array $params): bool => ($params['route'] ?? null) === 'cms.page.detail'
                && ($params['source'] ?? null) === $testSettings['listName']
                && ($params['arguments'] ?? null) === ['modelId' => 123, 'relations' => []],
        );
});

it('copies a page with elements', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['en' => 'Original Page', 'de' => 'Original Seite'],
        'slug' => ['en' => '/original-page', 'de' => '/original-seite'],
    ]);

    ElementPage::create([
        'page_id' => $model->id,
        'element_key' => 'text_block_1_column',
        'sort' => 1,
        'data' => json_encode(['text' => ['en' => 'Hello']]),
    ]);

    $component = Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->call('copy')
        ->assertOk()
        ->assertDispatched('listRefresh');

    $this->assertDatabaseCount('pages', 2);

    $copiedPage = Page::where('id', '!=', $model->id)->first();
    expect($copiedPage->name['en'])->toBe('Original Page 2');
    expect($copiedPage->name['de'])->toBe('Original Seite 2');
    expect($copiedPage->slug['en'])->toContain('/original-page-2');
    expect($copiedPage->slug['de'])->toContain('/original-seite-2');

    $copiedElements = ElementPage::where('page_id', $copiedPage->id)->get();
    expect($copiedElements)->toHaveCount(1);
    expect($copiedElements->first()->getRawOriginal('element_key'))->toBe('text_block_1_column');
});

it('copies a collection page and appends 2 to data title', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    $collection = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'BENEFITS',
        'name' => 'Benefits',
    ]);

    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_id' => $collection->id,
        'name' => null,
        'slug' => null,
        'data' => ['title' => 'Original Benefit', 'description' => 'Some text'],
    ]);

    $component = Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->call('copy')
        ->assertOk()
        ->assertDispatched('listRefresh');

    $copiedPage = Page::where('id', '!=', $model->id)->first();
    expect($copiedPage->data['title'])->toBe('Original Benefit 2');
    expect($copiedPage->data['description'])->toBe('Some text');
});

it('sets a table key for the list', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    Livewire::test($testSettings['listName'])
        ->assertNotSet('listId', '');
});
