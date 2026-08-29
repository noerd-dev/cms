<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('stores a form request via API using user api token', function (): void {
    // Arrange: create a tenant and a user with api token
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create([
        'api_token' => 'test_token_123',
    ]);
    TenantHelper::setSelectedTenantId($tenant->id);

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

it('stores the form request under the tenant resolved for the token user', function (): void {
    // CmsApiAuth reads $user->selected_tenant_id, which on NoerdUser is a
    // session-backed ACCESSOR (TenantHelper::getSelectedTenantId()) — the raw
    // noerd_users.selected_tenant_id column is never consulted. The stored row
    // must carry the tenant the middleware resolved for the token user, not a
    // stale value in the user's database column.
    $resolvedTenant = Tenant::factory()->create();
    $staleColumnTenant = Tenant::factory()->create();

    $user = NoerdUser::factory()->create(['api_token' => 'tenant_precedence_token']);

    // Bypass the Eloquent mutator (which would write the session) to plant a
    // DIFFERENT tenant id in the raw database column.
    DB::table('noerd_users')->where('id', $user->id)->update([
        'selected_tenant_id' => $staleColumnTenant->id,
    ]);

    TenantHelper::setSelectedTenantId($resolvedTenant->id);

    $response = $this->withHeader('Authorization', 'Bearer tenant_precedence_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'contact',
            'data' => ['name' => 'Jane Doe'],
        ]);

    $response->assertCreated();
    $this->assertDatabaseHas('form_requests', [
        'id' => $response->json('id'),
        'tenant_id' => $resolvedTenant->id,
    ]);
    $this->assertDatabaseMissing('form_requests', [
        'id' => $response->json('id'),
        'tenant_id' => $staleColumnTenant->id,
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
