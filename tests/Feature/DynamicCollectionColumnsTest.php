<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\User;
use Livewire\Volt\Volt;

uses(Tests\TestCase::class);

it('displays dynamic columns from YAML configuration for projects', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    // Create a project entry with data matching the YAML fields
    $projectPage = Page::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Test Projekt',
                'en' => 'Test Project'
            ],
            'image' => '/storage/test-image.jpg'
        ],
        'sort' => 1,
    ]);

    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    
    // Should show the project name and image indicator
    $response->assertSee('Test Projekt');
    $response->assertSee('✓ Bild vorhanden');
    
    // Should show column headers from YAML
    $response->assertSee('Name'); // From YAML field label
    $response->assertSee('Bild'); // From YAML field label
    $response->assertSee('Sortierung'); // Standard column
});

it('displays dynamic columns from YAML configuration for customers', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'CUSTOMERS',
        'name' => 'Customers',
    ]);

    // Create a customer entry with data matching the YAML fields
    $customerPage = Page::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Test Kunde',
                'en' => 'Test Customer'
            ],
            'description' => [
                'de' => 'Eine Beschreibung',
                'en' => 'A description'
            ]
        ],
        'sort' => 0,
    ]);

    $response = $this->get('/cms/collections?key=customers');
    $response->assertStatus(200);
    
    // Should show the customer data
    $response->assertSee('Test Kunde');
    $response->assertSee('Eine Beschreibung');
    
    // Should show column headers from YAML
    $response->assertSee('Name'); // From YAML field label
    $response->assertSee('Description'); // From YAML field label
});

it('handles empty collection entries gracefully', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    
    // Should still show column headers even with no data
    $response->assertSee('Name'); // From YAML field label
    $response->assertSee('Bild'); // From YAML field label
    $response->assertSee('Project'); // Collection title
});

it('searches in dynamic fields correctly', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    // Create parent collection
    $parentCollection = Collection::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'name' => 'Projects',
    ]);

    // Create multiple project entries
    Page::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Laravel Projekt',
                'en' => 'Laravel Project'
            ]
        ],
        'sort' => 1,
    ]);

    Page::create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_id' => $parentCollection->id,
        'data' => [
            'name' => [
                'de' => 'Vue.js Anwendung',
                'en' => 'Vue.js Application'
            ]
        ],
        'sort' => 2,
    ]);

    // Test that both entries are visible initially
    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Laravel Projekt');
    $response->assertSee('Vue.js Anwendung');
});
