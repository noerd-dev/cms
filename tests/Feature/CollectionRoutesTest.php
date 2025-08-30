<?php

use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

it('can access collection entries route with key parameter', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Project'); // Should show the collection title
});

it('redirects to collection-files when no key is provided', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $response = $this->get('/cms/collections');
    $response->assertRedirect(route('cms.collection-files'));
});

it('can access collection files route', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $response = $this->get('/cms/collection-files');
    $response->assertStatus(200);
    $response->assertSee('Collections'); // Should show the YAML files table
});

it('collection entries route shows correct collection data', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    // Test different collection keys
    $collections = ['projects', 'services', 'customers', 'contacts', 'sliders'];
    
    foreach ($collections as $key) {
        $response = $this->get("/cms/collections?key={$key}");
        $response->assertStatus(200);
        // Should not show YAML file management interface
        $response->assertDontSee('Collection-Datei wurde erfolgreich gelöscht');
    }
});

it('collection files route shows YAML management interface', function (): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $response = $this->get('/cms/collection-files');
    $response->assertStatus(200);
    
    // Should show YAML file management
    $response->assertSee('Collections');
    // Should show existing collection files
    $response->assertSee('.yml');
});
