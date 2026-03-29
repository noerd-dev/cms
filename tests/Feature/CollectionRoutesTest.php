<?php

use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

// Mock CollectionHelper via Laravel's container
beforeEach(function (): void {
    $this->mock(CollectionHelper::class, function ($mock): void {
        // Mock resolveCollectionFields for various collections used in tests
        $mock->shouldReceive('resolveCollectionFields')
            ->with('projects')
            ->andReturn([
                'title' => 'Project',
                'titleList' => 'Projects',
                'fields' => [
                    ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText'],
                ],
            ]);

        $mock->shouldReceive('resolveCollectionFields')
            ->with('services')
            ->andReturn([
                'title' => 'Test Collection',
                'titleList' => 'Test Collections',
                'fields' => [
                    ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText'],
                ],
            ]);

        $mock->shouldReceive('resolveCollectionFields')
            ->with('customers')
            ->andReturn([
                'title' => 'Test Collection',
                'titleList' => 'Test Collections',
                'fields' => [
                    ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText'],
                ],
            ]);

        $mock->shouldReceive('resolveCollectionFields')
            ->with('contacts')
            ->andReturn([
                'title' => 'Test Collection',
                'titleList' => 'Test Collections',
                'fields' => [
                    ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText'],
                ],
            ]);

        $mock->shouldReceive('resolveCollectionFields')
            ->with('sliders')
            ->andReturn([
                'title' => 'Test Collection',
                'titleList' => 'Test Collections',
                'fields' => [
                    ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText'],
                ],
            ]);
    });
});

it('can access collection entries route with key parameter', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/collections?key=projects');
    $response->assertStatus(200);
    $response->assertSee('Project'); // Should show the collection title
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
