<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Noerd\Cms\Jobs\SendFormConfirmationEmail;
use Noerd\Cms\Models\FormType;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('stores a form request via API using user api token', function (): void {
    // Arrange: create a tenant, its form type and a user with api token
    $tenant = Tenant::factory()->create();
    $formType = FormType::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'contact',
    ]);
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
        'form_type_id' => $formType->id,
    ]);
});

it('rejects a submission for an unknown form type', function (): void {
    $tenant = Tenant::factory()->create();
    NoerdUser::factory()->create(['api_token' => 'unknown_form_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    $this->withHeader('Authorization', 'Bearer unknown_form_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'does-not-exist',
            'data' => ['name' => 'Max'],
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('form_requests', ['form' => 'does-not-exist']);
});

it('validates the submission against the form YAML and dispatches the email job', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $ymlPath = 'app-configs/cms/forms-api-test-' . getmypid() . '.yml';
    file_put_contents(base_path($ymlPath), implode("\n", [
        'key: contact',
        'title: Contact',
        'send_email: true',
        'fields:',
        '  - name: email',
        '    label: Email',
        '    validation:',
        '      - required',
        '      - email',
    ]));

    FormType::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'contact',
        'send_email' => true,
        'yml_path' => $ymlPath,
    ]);
    NoerdUser::factory()->create(['api_token' => 'yaml_validation_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    // Invalid per the YAML rules: no email field
    $this->withHeader('Authorization', 'Bearer yaml_validation_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'contact',
            'data' => ['name' => 'Max', 'undeclared' => 'dropped'],
        ])
        ->assertStatus(422);

    Queue::assertNothingPushed();

    // Valid submission: stored (only declared fields) and job dispatched
    $response = $this->withHeader('Authorization', 'Bearer yaml_validation_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'contact',
            'data' => ['email' => 'max@example.com', 'undeclared' => 'dropped'],
        ]);

    $response->assertCreated();
    $this->assertDatabaseHas('form_requests', [
        'id' => $response->json('id'),
        'data->email' => 'max@example.com',
    ]);
    $this->assertDatabaseMissing('form_requests', [
        'id' => $response->json('id'),
        'data->undeclared' => 'dropped',
    ]);

    Queue::assertPushed(SendFormConfirmationEmail::class, 1);

    unlink(base_path($ymlPath));
});

it('stores the form request under the tenant resolved for the token user', function (): void {
    // CmsApiAuth reads $user->selected_tenant_id, which on NoerdUser is a
    // session-backed ACCESSOR (TenantHelper::getSelectedTenantId()) — noerd_users
    // carries no column of its own and the persisted copy on the user's settings
    // row is only the starting point restored at login. The stored form request
    // must carry the tenant the middleware resolved, not a stale persisted value.
    $resolvedTenant = Tenant::factory()->create();
    $stalePersistedTenant = Tenant::factory()->create();
    FormType::factory()->create([
        'tenant_id' => $resolvedTenant->id,
        'key' => 'contact',
    ]);

    $user = NoerdUser::factory()->create(['api_token' => 'tenant_precedence_token']);

    // Plant a DIFFERENT tenant id in the persisted settings row. Nobody is
    // authenticated here, so TenantHelper only writes the session and leaves it.
    $user->setting->update(['selected_tenant_id' => $stalePersistedTenant->id]);

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
        'tenant_id' => $stalePersistedTenant->id,
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
