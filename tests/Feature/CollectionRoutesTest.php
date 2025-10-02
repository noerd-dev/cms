<?php

use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

/**
 * @group no-parallel
 */

// Mock CollectionHelper to avoid file system dependencies
beforeEach(function (): void {
    // Create an overload mock for better parallel test isolation
    $mock = \Mockery::mock('overload:' . CollectionHelper::class);

    // Mock getCollectionFields for various collections used in tests
    $mock->shouldReceive('getCollectionFields')
        ->with('projects')
        ->andReturn([
            'title' => 'Project',
            'titleList' => 'Projects',
            'buttonList' => 'New Project',
            'fields' => [
                ['name' => 'model.name', 'label' => 'Name', 'type' => 'translatableText'],
            ],
        ]);

    $mock->shouldReceive('getCollectionFields')
        ->with('services')
        ->andReturn([
            'title' => 'Test Collection',
            'titleList' => 'Test Collections',
            'buttonList' => 'New Entry',
            'fields' => [
                ['name' => 'model.name', 'label' => 'Name', 'type' => 'translatableText'],
            ],
        ]);

    $mock->shouldReceive('getCollectionFields')
        ->with('customers')
        ->andReturn([
            'title' => 'Test Collection',
            'titleList' => 'Test Collections',
            'buttonList' => 'New Entry',
            'fields' => [
                ['name' => 'model.name', 'label' => 'Name', 'type' => 'translatableText'],
            ],
        ]);

    $mock->shouldReceive('getCollectionFields')
        ->with('contacts')
        ->andReturn([
            'title' => 'Test Collection',
            'titleList' => 'Test Collections',
            'buttonList' => 'New Entry',
            'fields' => [
                ['name' => 'model.name', 'label' => 'Name', 'type' => 'translatableText'],
            ],
        ]);

    $mock->shouldReceive('getCollectionFields')
        ->with('sliders')
        ->andReturn([
            'title' => 'Test Collection',
            'titleList' => 'Test Collections',
            'buttonList' => 'New Entry',
            'fields' => [
                ['name' => 'model.name', 'label' => 'Name', 'type' => 'translatableText'],
            ],
        ]);
});

it('can access collection entries route with key parameter', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Project'); // Should show the collection title
});

it('redirects to collection-files when no key is provided', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/collections');
    $response->assertRedirect(route('cms.collection-files'));
});

it('can access collection files route', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/collection-files');
    $response->assertStatus(200);
    $response->assertSee('Collections'); // Should show the YAML files table
});

it('collection entries route shows correct collection data', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Test different collection keys (using mocked collections)
    $collections = ['projects', 'services', 'customers', 'contacts', 'sliders'];

    foreach ($collections as $key) {
        $response = $this->get("/cms/collections?key={$key}");
        $response->assertStatus(200);
        // Should not show YAML file management interface
        $response->assertDontSee('Collection-Datei wurde erfolgreich gelöscht');
    }
});

it('collection files route shows YAML management interface', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/collection-files');
    $response->assertStatus(200);

    // Should show YAML file management
    $response->assertSee('Collections');
    // Should show existing collection files
    $response->assertSee('.yml');
});
