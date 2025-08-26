<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('can create many-to-many relationship between page and collections', function (): void {
    $user = User::factory()->withContentModule()->create();

    // Create a page
    $page = Page::create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => json_encode(['de' => 'Test Page', 'en' => 'Test Page']),
        'slug' => json_encode(['de' => '/test-page', 'en' => '/test-page']),
    ]);

    // Create collections
    $collection1 = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    $collection2 = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'SERVICES',
        'name' => 'Services',
    ]);

    // Attach collections to page
    $page->collections()->attach([
        $collection1->id => [
            'tenant_id' => $user->selected_tenant_id,
            'sort_order' => 1,
        ],
        $collection2->id => [
            'tenant_id' => $user->selected_tenant_id,
            'sort_order' => 2,
        ],
    ]);

    // Verify relationships
    expect($page->collections()->count())->toBe(2);
    expect($collection1->pages()->count())->toBe(1);
    expect($collection2->pages()->count())->toBe(1);

    // Verify pivot data
    $pageCollection = $page->collections()->first();
    expect($pageCollection->pivot->tenant_id)->toBe($user->selected_tenant_id);
    expect($pageCollection->pivot->sort_order)->toBe(1);
});

it('can sync collections on a page', function (): void {
    $user = User::factory()->withContentModule()->create();

    // Create a page
    $page = Page::create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => json_encode(['de' => 'Test Page', 'en' => 'Test Page']),
        'slug' => json_encode(['de' => '/test-page', 'en' => '/test-page']),
    ]);

    // Create collections
    $collection1 = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    $collection2 = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'SERVICES',
        'name' => 'Services',
    ]);

    $collection3 = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'CONTACTS',
        'name' => 'Contacts',
    ]);

    // Initial sync
    $page->collections()->sync([
        $collection1->id => [
            'tenant_id' => $user->selected_tenant_id,
            'sort_order' => 1,
        ],
        $collection2->id => [
            'tenant_id' => $user->selected_tenant_id,
            'sort_order' => 2,
        ],
    ]);

    expect($page->collections()->count())->toBe(2);

    // Re-sync with different collections
    $page->collections()->sync([
        $collection2->id => [
            'tenant_id' => $user->selected_tenant_id,
            'sort_order' => 1,
        ],
        $collection3->id => [
            'tenant_id' => $user->selected_tenant_id,
            'sort_order' => 2,
        ],
    ]);

    expect($page->collections()->count())->toBe(2);
    expect($page->collections()->pluck('collection_key')->toArray())->toContain('SERVICES', 'CONTACTS');
});
