<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

/**
 * @group no-parallel
 */

beforeEach(function () {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);
    $this->user = $user;
    $this->tenant = $tenant;

    // Create parent collection
    $this->collection = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'MITARBEITER',
        'name' => 'Mitarbeiter',
    ]);

    // Mock CollectionHelper to avoid conflicts with other test files
    $mock = \Mockery::mock('overload:' . CollectionHelper::class);
    $mock->shouldReceive('getCollectionFields')
        ->with('mitarbeiter')
        ->andReturn([
            'title' => 'Mitarbeiter',
            'hasPage' => true,
            'fields' => [
                ['name' => 'pageData.title', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'pageData.title2', 'label' => 'Titel', 'type' => 'text'],
                ['name' => 'pageData.phone', 'label' => 'Telefon', 'type' => 'text'],
                ['name' => 'pageData.email', 'label' => 'E-Mail', 'type' => 'text'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'image'],
            ],
        ]);
});

it('preserves manually edited slug when saving collection page', function () {
    // Create a page with initial slug
    $page = Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_id' => $this->collection->id,
        'name' => ['de' => 'Gerit Woerner'],
        'slug' => ['de' => '/gerit-woerner'],
        'data' => ['title' => 'Gerit Woerner', 'title2' => 'Steuerberater'],
        'layout' => 'weblayout',
    ]);

    // Open the page and change the slug manually
    $component = Volt::test('page-detail', [
        'pageId' => $page->id,
        'collectionKey' => 'mitarbeiter',
    ])
        ->set('pageData.slug.de', '/gerit-woerner-updated')  // Manual slug change
        ->call('store')
        ->assertOk();

    // Verify the slug was preserved (not regenerated from name)
    $updatedPage = Page::find($page->id);
    expect($updatedPage->slug)->toBe(['de' => '/gerit-woerner-updated']);
});

it('auto-generates slug only when slug is empty', function () {
    // Create a page with empty slug
    $page = Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_id' => $this->collection->id,
        'name' => ['de' => ''],
        'slug' => ['de' => ''],
        'data' => ['title' => 'Test Person', 'title2' => 'Titel'],
        'layout' => 'weblayout',
    ]);

    // Set a name but leave slug empty - should auto-generate
    $component = Volt::test('page-detail', [
        'pageId' => $page->id,
        'collectionKey' => 'mitarbeiter',
    ])
        ->set('pageData.name.de', 'Max Mustermann')
        ->set('pageData.slug.de', '')  // Empty slug
        ->call('store')
        ->assertOk();

    // Verify slug was auto-generated (may have language prefix based on tenant settings)
    $updatedPage = Page::find($page->id);
    expect($updatedPage->slug['de'])->toContain('max-mustermann');
});

it('stores only collection-specific fields in data column', function () {
    // Create a page
    $page = Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_id' => $this->collection->id,
        'name' => ['de' => 'Test Person'],
        'slug' => ['de' => '/test-person'],
        'data' => ['title' => 'Old Title'],
        'layout' => 'weblayout',
    ]);

    // Update collection fields
    $component = Volt::test('page-detail', [
        'pageId' => $page->id,
        'collectionKey' => 'mitarbeiter',
    ])
        ->set('pageData.title', 'New Title')
        ->set('pageData.title2', 'New Subtitle')
        ->set('pageData.phone', '+49 123 456')
        ->set('pageData.email', 'test@example.com')
        ->call('store')
        ->assertOk();

    // Verify data column contains only collection fields
    $updatedPage = Page::find($page->id);
    $data = $updatedPage->data;

    // Should have collection fields
    expect($data)->toHaveKey('title');
    expect($data)->toHaveKey('title2');
    expect($data)->toHaveKey('phone');
    expect($data)->toHaveKey('email');

    // Should NOT have page fields
    expect($data)->not->toHaveKey('name');
    expect($data)->not->toHaveKey('slug');
    expect($data)->not->toHaveKey('layout');
    expect($data)->not->toHaveKey('is_active');
    expect($data)->not->toHaveKey('tenant_id');
    expect($data)->not->toHaveKey('collection_id');
});
