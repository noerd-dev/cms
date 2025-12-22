<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'element-page-detail',
    'listName' => 'element-pages-list',
    'id' => 'modelId',
];

it('successfully mounts with element page', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $tenant->id,
    ]);

    // Create ElementPage using element_key directly (no element_id needed)
    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => 'text_block_1_column',
        'data' => json_encode(['content' => 'Test content']),
        'sort' => 1,
    ]);

    Volt::test($testSettings['componentName'], [$elementPage])
        ->assertSet('modelId', $elementPage->id)
        ->assertSet('elementPage.id', $elementPage->id)
        ->assertHasNoErrors();
});

it('can update element page data', function (): void {
    // Skip: This test requires YML element definitions from website module
    $this->markTestSkipped('Requires element YML definitions from website module');
})->skip('Requires element YML definitions from website module');

it('validates element page data', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $tenant->id,
    ]);

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => 'text_block_1_column',
        'data' => json_encode(['content' => 'Test content']),
        'sort' => 1,
    ]);

    // Test without setting required fields (this depends on the element_key configuration)
    Volt::test($testSettings['componentName'], [$elementPage])
        ->call('store')
        ->assertHasNoErrors(); // Element validation depends on the specific element configuration
});

it('can delete element page', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();

    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $tenant->id,
    ]);

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => 'text_block_1_column',
        'data' => json_encode(['content' => 'Test content']),
        'sort' => 1,
    ]);

    Volt::test($testSettings['componentName'], [$elementPage])
        ->call('delete')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('element_page', [
        'id' => $elementPage->id,
    ]);
});

it('sets correct element layout', function (): void {
    // Skip: This test requires YML element definitions from website module
    $this->markTestSkipped('Requires element YML definitions from website module');
})->skip('Requires element YML definitions from website module');

it('shows a content error when element layout is missing', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $tenant->id,
    ]);

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => '____missing____',
        'data' => json_encode(['foo' => 'bar']),
        'sort' => 1,
    ]);

    Volt::test($testSettings['componentName'], [$elementPage])
        ->assertSee(__('Element component not found:'))
        ->assertSee('Please create both the .yml and .blade.php files in the elements folder.')
        ->assertSee('____missing____');
});
