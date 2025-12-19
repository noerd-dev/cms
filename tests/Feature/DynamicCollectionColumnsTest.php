<?php

use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

/**
 * @group no-parallel
 */

// Mock CollectionHelper to ensure consistent behavior across test runs
beforeEach(function (): void {
    $mock = \Mockery::mock('overload:' . CollectionHelper::class);

    // Mock getCollectionFields for projects
    $mock->shouldReceive('getCollectionFields')
        ->with('projects')
        ->andReturn([
            'title' => 'Project',
            'titleList' => 'Projects',
            'buttonList' => 'New Project',
            'hasPage' => true,
            'fields' => [
                ['name' => 'model.name', 'label' => 'Name', 'type' => 'translatableText'],
                ['name' => 'image', 'label' => 'Image', 'type' => 'image'],
            ],
        ]);

    // Mock getCollectionFields for customers
    $mock->shouldReceive('getCollectionFields')
        ->with('customers')
        ->andReturn([
            'title' => 'Customer',
            'titleList' => 'Customers',
            'buttonList' => 'New Customer',
            'hasPage' => false,
            'fields' => [
                ['name' => 'model.name', 'label' => 'Name', 'type' => 'translatableText'],
                ['name' => 'model.description', 'label' => 'Description', 'type' => 'translatableText'],
            ],
        ]);
});

it('displays dynamic columns from YAML configuration for projects', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $tenant->id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    // Create a project entry with data matching the mocked fields
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

    // Create a customer entry with data matching the mocked fields
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
    $response->assertSee('Description');
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
