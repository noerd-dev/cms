<?php


use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
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

    // Mock CollectionHelper via Laravel's container
    $this->mock(CollectionHelper::class, function ($mock): void {
        $mock->shouldReceive('resolveCollectionFields')
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
});

it('preserves manually edited slug when saving collection page', function (): void {
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
    $component = Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail', ['collectionKey' => 'mitarbeiter'])
        ->set('pageData.slug.de', '/gerit-woerner-updated')  // Manual slug change
        ->call('store')
        ->assertOk();

    // Verify the slug was preserved (not regenerated from name)
    $updatedPage = Page::find($page->id);
    expect($updatedPage->slug)->toBe(['de' => '/gerit-woerner-updated']);
});

it('auto-generates slug only when slug is empty', function (): void {
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
    $component = Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail', ['collectionKey' => 'mitarbeiter'])
        ->set('pageData.name.de', 'Max Mustermann')
        ->set('pageData.slug.de', '')  // Empty slug
        ->call('store')
        ->assertOk();

    // Verify slug was auto-generated (may have language prefix based on tenant settings)
    $updatedPage = Page::find($page->id);
    expect($updatedPage->slug['de'])->toContain('max-mustermann');
});

it('stores only collection-specific fields in data column', function (): void {
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
    $component = Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail', ['collectionKey' => 'mitarbeiter'])
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

it('does not overwrite slug column with stale data.slug value on mount', function (): void {
    // Create a page where the data column contains an outdated slug
    // This simulates the bug where editing a page would show the wrong slug
    $page = Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_id' => $this->collection->id,
        'name' => ['de' => 'Steuerberater'],
        'slug' => ['de' => '/steuerberaterwirtschaftspruefer-mwd'],  // Correct slug in column
        'data' => [
            'title' => 'Steuerberater',
            'slug' => ['de' => '/stellenangebot'],  // Stale/old slug in data - should NOT overwrite
        ],
        'layout' => 'weblayout',
    ]);

    // Mount the page-detail component
    $component = Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail', ['collectionKey' => 'mitarbeiter']);

    // Verify the correct slug from the column is displayed, not the stale one from data
    $pageData = $component->get('pageData');
    expect($pageData['slug']['de'])->toBe('/steuerberaterwirtschaftspruefer-mwd');
});

it('does not overwrite core page fields from data column on mount', function (): void {
    // Create a page with stale core fields in the data column
    $page = Page::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_id' => $this->collection->id,
        'name' => ['de' => 'Correct Name'],
        'slug' => ['de' => '/correct-slug'],
        'layout' => 'correct-layout',
        'sort' => 5,
        'data' => [
            'title' => 'Collection Field',
            'name' => ['de' => 'Stale Name'],      // Should NOT overwrite
            'slug' => ['de' => '/stale-slug'],     // Should NOT overwrite
            'layout' => 'stale-layout',            // Should NOT overwrite
            'sort' => 99,                          // Should NOT overwrite
        ],
    ]);

    // Mount the page-detail component
    $component = Livewire::withUrlParams(['pageId' => $page->id])
        ->test('page-detail', ['collectionKey' => 'mitarbeiter']);

    // Verify core fields are preserved from columns, not overwritten by data
    $pageData = $component->get('pageData');
    expect($pageData['name']['de'])->toBe('Correct Name');
    expect($pageData['slug']['de'])->toBe('/correct-slug');
    expect($pageData['layout'])->toBe('correct-layout');
    expect($pageData['sort'])->toBe(5);

    // Collection-specific field should still be merged
    expect($pageData['title'])->toBe('Collection Field');
});
