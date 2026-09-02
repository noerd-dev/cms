<?php

declare(strict_types=1);

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;
use Noerd\Helpers\NoerdAuth;
use Tests\TestCase;

uses(TestCase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

$testSettings = [
    'componentName' => 'cms::page-detail',
    'listName' => 'cms::pages-list',
    'id' => 'modelId',
    'urlParam' => 'pageId',
];

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user, NoerdAuth::guardName());
    $this->createElementFixtures();
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('validates the data', function () use ($testSettings): void {
    $user = $this->user;

    // Test with invalid data (empty name array)
    Livewire::test($testSettings['componentName'])
        ->set('detailData.name', [])
        ->call('store')
        ->assertHasErrors(['detailData.name']);
});

it('successfully stores the data', function () use ($testSettings): void {
    $user = $this->user;

    $component = Livewire::test($testSettings['componentName'])
        ->set('detailData.name.en', 'Test Page')
        ->set('detailData.name.de', 'Test Seite')
        ->set('detailData.layout', 'weblayout')
        ->call('store')
        ->assertOk();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $user->selected_tenant_id,
        'name->en' => 'Test Page',
        'name->de' => 'Test Seite',
        'layout' => 'weblayout',
    ]);
});

it('successfully deletes a page', function () use ($testSettings): void {
    $user = $this->user;
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

it('opens and stores existing page', function () use ($testSettings): void {
    $user = $this->user;
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
        'name->en' => 'New Page',
        'name->de' => 'Neue Seite',
    ]);
});

it('dispatches table action from pages table', function () use ($testSettings): void {
    $user = $this->user;

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
    $user = $this->user;
    $model = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => ['en' => 'Original Page', 'de' => 'Original Seite'],
        'slug' => ['en' => '/original-page', 'de' => '/original-seite'],
    ]);

    ElementPage::create([
        'page_id' => $model->id,
        'element_key' => $this->zzTextElementKey(),
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
    expect($copiedElements->first()->getRawOriginal('element_key'))->toBe($this->zzTextElementKey());
});

it('copies a collection page and appends 2 to data title', function () use ($testSettings): void {
    $user = $this->user;

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
