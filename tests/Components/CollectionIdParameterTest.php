<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    // Mock CollectionHelper via Laravel's container
    $this->mock(CollectionHelper::class, function ($mock): void {
        $mock->shouldReceive('resolveCollectionFields')
            ->with('standort')
            ->andReturn([
                'title' => 'Standort',
                'titleList' => 'Standorte',
                'hasPage' => true,
                'fields' => [
                    ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText'],
                ],
            ]);

        $mock->shouldReceive('resolveCollectionFields')
            ->with('mitarbeiter')
            ->andReturn([
                'title' => 'Mitarbeiter',
                'titleList' => 'Mitarbeiter',
                'hasPage' => true,
                'fields' => [
                    ['name' => 'detailData.title', 'label' => 'Name', 'type' => 'text'],
                ],
            ]);

        $mock->shouldReceive('resolveCollectionFields')
            ->with(null)
            ->andReturn(null);
    });
});

it('accepts collection key as string (existing behavior)', function (): void {
    // Create a collection with STANDORT key
    $collection = Collection::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_key' => 'STANDORT',
    ]);

    // Test with string key
    $response = $this->get(route('cms.collections') . '?key=standort');

    $response->assertStatus(200);
    $response->assertSee('Standort');
});

it('accepts collection ID as integer parameter (new behavior)', function (): void {
    // Create a collection with STANDORT key
    $collection = Collection::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_key' => 'STANDORT',
    ]);

    // Test with numeric ID
    $response = $this->get(route('cms.collections') . '?key=' . $collection->id);

    $response->assertStatus(200);
    $response->assertSee('Standort');
});

it('accepts collection ID as string parameter (new behavior)', function (): void {
    // Create a collection with STANDORT key
    $collection = Collection::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_key' => 'STANDORT',
    ]);

    // Test with numeric string ID
    $response = $this->get(route('cms.collections') . '?key=' . (string) $collection->id);

    $response->assertStatus(200);
    $response->assertSee('Standort');
});

it('resolves collection key correctly in livewire component', function (): void {
    // Create a collection with STANDORT key
    $collection = Collection::factory()->create([
        'tenant_id' => $this->user->selected_tenant_id,
        'collection_key' => 'STANDORT',
    ]);

    // Test component with integer ID
    Livewire::test('cms::collection-entries-list', ['collectionKey' => $collection->id])
        ->assertSet('collectionKey', 'standort') // Should be resolved to lowercase string
        ->assertStatus(200);
});

it('resolves collection key correctly when passed as string', function (): void {
    // Test component with string key
    Livewire::test('cms::collection-entries-list', ['collectionKey' => 'mitarbeiter'])
        ->assertSet('collectionKey', 'mitarbeiter')
        ->assertStatus(200);
});

it('handles null collection key gracefully', function (): void {
    Livewire::test('cms::collection-entries-list', ['collectionKey' => null])
        ->assertSet('collectionKey', null)
        ->assertSee(__('Please select a collection from the navigation.'))
        ->assertStatus(200);
});

it('handles non-existent collection ID gracefully', function (): void {
    Livewire::test('cms::collection-entries-list', ['collectionKey' => 999999])
        ->assertSet('collectionKey', null)
        ->assertSee(__('Please select a collection from the navigation.'))
        ->assertStatus(200);
});
