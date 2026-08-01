<?php


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

    Livewire::test($testSettings['componentName'], ['modelId' => $elementPage->id])
        ->assertSet('modelId', $elementPage->id)
        ->assertSet('elementPage.id', $elementPage->id)
        ->assertHasNoErrors();
});

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
    Livewire::test($testSettings['componentName'], ['modelId' => $elementPage->id])
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

    Livewire::test($testSettings['componentName'], ['modelId' => $elementPage->id])
        ->call('delete')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('element_page', [
        'id' => $elementPage->id,
    ]);
});

it('uses passed modelId instead of URL id parameter', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

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

    // Simulate the bug: URL has ?id=999 (Page ID), but component receives actual ElementPage ID
    Livewire::withUrlParams(['id' => 999])
        ->test($testSettings['componentName'], ['modelId' => $elementPage->id])
        ->assertSet('modelId', $elementPage->id)
        ->assertSet('elementPage.id', $elementPage->id)
        ->call('store')
        ->assertHasNoErrors();
});

it('store handles non-existent element page gracefully', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

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

    // Mount with valid element, then set modelId to non-existent ID
    Livewire::test($testSettings['componentName'], ['modelId' => $elementPage->id])
        ->set('modelId', 99999)
        ->call('store')
        ->assertHasNoErrors();
});

it('delete handles non-existent element page gracefully', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

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

    // Mount with valid element, then set modelId to non-existent ID
    Livewire::test($testSettings['componentName'], ['modelId' => $elementPage->id])
        ->set('modelId', 99999)
        ->call('delete')
        ->assertHasNoErrors();

    // Original element should still exist
    $this->assertDatabaseHas('element_page', ['id' => $elementPage->id]);
});

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

    Livewire::test($testSettings['componentName'], ['modelId' => $elementPage->id])
        ->assertSee(__('Element component not found:'))
        ->assertSee(__('Please create both the .yml and .blade.php files in the elements folder.'))
        ->assertSee('____missing____');
});
