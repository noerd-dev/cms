<?php

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

$testSettings = [
    'componentName' => 'collection-component',
    'listName' => 'collections-table',
    'id' => 'modelId',
];

it('test the collections route with key parameter', function (): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    $response = $this->get(route('cms.collections') . '?key=projects');
    $response->assertStatus(200);
});

it('creates a collection directly with factory', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    $this->actingAs($user);

    $collection = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'WEBSITES',
        'data' => json_encode([
            'name' => ['de' => 'Test Projekt', 'en' => 'Test Project'],
        ]),
        'sort' => 10,
    ]);

    $this->assertDatabaseHas('collections', [
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'WEBSITES',
        'sort' => 10,
    ]);

    $decodedData = json_decode($collection->data, true);
    $this->assertEquals('Test Projekt', $decodedData['name']['de']);
    $this->assertEquals('Test Project', $decodedData['name']['en']);
});

it('tests collection model relationships', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    $page = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => '{"de":"Test Page","en":"Test Page"}',
    ]);

    $collection = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'page_id' => $page->id,
    ]);

    // Test relationship
    $this->assertNotNull($collection->page);
    $this->assertEquals($page->id, $collection->page->id);
});

it('updates collection data', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    $collection = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'data' => json_encode([
            'name' => ['de' => 'Altes Projekt', 'en' => 'Old Project'],
        ]),
        'sort' => 1,
    ]);

    // Update collection data
    $collection->update([
        'data' => json_encode([
            'name' => ['de' => 'Neues Projekt', 'en' => 'New Project'],
        ]),
        'sort' => 5,
    ]);

    $collection->refresh();
    $this->assertEquals(5, $collection->sort);

    $decodedData = json_decode($collection->data, true);
    $this->assertEquals('Neues Projekt', $decodedData['name']['de']);
    $this->assertEquals('New Project', $decodedData['name']['en']);
});

it('deletes collection', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    $collection = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
    ]);

    $collectionId = $collection->id;
    $collection->delete();

    $this->assertDatabaseMissing('collections', [
        'id' => $collectionId,
    ]);
});

it('creates collection with page association', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    // Create page first
    $page = Page::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => '{"de":"CollectionPage","en":"CollectionPage"}',
    ]);

    // Create collection with page association
    $collection = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'page_id' => $page->id,
    ]);

    $this->assertNotNull($collection->page_id);
    $this->assertEquals($page->id, $collection->page_id);

    $this->assertDatabaseHas('pages', [
        'id' => $page->id,
        'name' => '{"de":"CollectionPage","en":"CollectionPage"}',
    ]);
});

it('tests collection factory without page', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    $collection = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'page_id' => null,
    ]);

    $this->assertNull($collection->page_id);
});

it('tests collection with sort functionality', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    $collection1 = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'sort' => 1,
    ]);

    $collection2 = Collection::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'collection_key' => 'PROJECTS',
        'sort' => 2,
    ]);

    $collections = Collection::where('tenant_id', $user->selected_tenant_id)
        ->where('collection_key', 'PROJECTS')
        ->orderBy('sort')
        ->get();

    $this->assertEquals(1, $collections->first()->sort);
    $this->assertEquals(2, $collections->last()->sort);
});
