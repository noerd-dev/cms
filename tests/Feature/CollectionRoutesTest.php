<?php

use Illuminate\Support\Facades\File;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

// Setup and teardown for YAML mocking
beforeEach(function (): void {
    // Create test YAML files with known content
    $collectionsPath = base_path('content/collections');

    // Backup existing files if they exist
    $this->originalProjectsYml = null;

    if (File::exists($collectionsPath . '/projects.yml')) {
        $this->originalProjectsYml = File::get($collectionsPath . '/projects.yml');
    }

    // Create test YAML files
    File::ensureDirectoryExists($collectionsPath);

    File::put(
        $collectionsPath . '/projects.yml',
        "title: 'Project'\n" .
        "titleList: 'Projects'\n" .
        "buttonList: 'New Project'\n" .
        "fields:\n" .
        "  - { name: model.name, label: Name, type: translatableText }\n",
    );
});

afterEach(function (): void {
    $collectionsPath = base_path('content/collections');

    // Restore original files or delete test files
    if ($this->originalProjectsYml !== null) {
        File::put($collectionsPath . '/projects.yml', $this->originalProjectsYml);
    } else {
        File::delete($collectionsPath . '/projects.yml');
    }
});

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
    // Create additional test YAML files for this test
    $collectionsPath = base_path('content/collections');
    $testCollections = ['services', 'customers', 'contacts', 'sliders'];

    foreach ($testCollections as $collection) {
        File::put(
            $collectionsPath . '/' . $collection . '.yml',
            "title: 'Test Collection'\n" .
            "titleList: 'Test Collections'\n" .
            "buttonList: 'New Entry'\n" .
            "fields:\n" .
            "  - { name: model.name, label: Name, type: translatableText }\n",
        );
    }

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

    // Cleanup test files
    $collectionsPath = base_path('content/collections');
    $testCollections = ['services', 'customers', 'contacts', 'sliders'];
    foreach ($testCollections as $collection) {
        if (File::exists($collectionsPath . '/' . $collection . '.yml')) {
            File::delete($collectionsPath . '/' . $collection . '.yml');
        }
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
