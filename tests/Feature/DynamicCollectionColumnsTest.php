<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('displays dynamic columns from YAML configuration for projects', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    // Create a project entry with data matching the YAML fields
    Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Test Projekt',
                'en' => 'Test Project',
            ],
            'image' => '/storage/test-image.jpg',
        ],
        'sort' => 1,
    ]);

    // Test using HTTP request
    // Note: Default language is 'en', so English values are displayed
    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Test Project');
    $response->assertSee('✓ Bild vorhanden');
    $response->assertSee('Name');
    $response->assertSee('Image');
    $response->assertSee('Sortierung');
});

it('displays dynamic columns from YAML configuration for customers', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'CUSTOMERS',
        'name' => 'Customers',
    ]);

    // Create a customer entry with data matching the YAML fields
    Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Test Kunde',
                'en' => 'Test Customer',
            ],
            'description' => [
                'de' => 'Eine Beschreibung',
                'en' => 'A description',
            ],
        ],
        'sort' => 0,
    ]);

    // Test using HTTP request
    // Note: Default language is 'en', so English values are displayed
    $response = $this->get('/cms/collections?key=customers');
    $response->assertStatus(200);
    $response->assertSee('Test Customer');
    $response->assertSee('A description');
    $response->assertSee('Name');
    $response->assertSee('Beschreibung'); // YAML label remains German
});

it('handles empty collection entries gracefully', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Test using HTTP request - should show column headers even with no data
    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Name');
    $response->assertSee('Image');
});

it('displays multiple entries correctly', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    // Create multiple project entries
    Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Laravel Projekt',
                'en' => 'Laravel Project',
            ],
        ],
        'sort' => 1,
    ]);

    Page::create([
        'tenant_id' => $tenant->id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Vue.js Anwendung',
                'en' => 'Vue.js Application',
            ],
        ],
        'sort' => 2,
    ]);

    // Test that both entries are visible (using English values as default language is 'en')
    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Laravel Project');
    $response->assertSee('Vue.js Application');
});
