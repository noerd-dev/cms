<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Models\Tenant;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('stores a form request via API using tenant api token', function (): void {
    // Arrange: create a tenant with api token
    $tenant = Tenant::factory()->create([
        'api_token' => 'test_token_123',
    ]);

    $payload = [
        'form' => 'contact',
        'data' => [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Hallo',
        ],
    ];

    // Act: call API with Bearer token
    $response = $this->withHeader('Authorization', 'Bearer test_token_123')
        ->postJson('/api/cms/form-requests', $payload);

    // Assert
    $response->assertCreated();
    $this->assertDatabaseHas('form_requests', [
        'tenant_id' => $tenant->id,
        'form' => 'contact',
    ]);
});

it('rejects requests with missing or invalid token', function (): void {
    $payload = [
        'form' => 'contact',
        'data' => ['x' => 'y'],
    ];

    $this->postJson('/api/cms/form-requests', $payload)
        ->assertStatus(401);

    $this->withHeader('Authorization', 'Bearer wrong')
        ->postJson('/api/cms/form-requests', $payload)
        ->assertStatus(401);
});


