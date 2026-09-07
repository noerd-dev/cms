<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Noerd\Cms\Jobs\SendFormConfirmationEmail;
use Noerd\Cms\Models\FormType;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

it('stores a form request via API using user api token', function (): void {
    // Arrange: create a tenant, its form type and a user with api token
    $tenant = $this->createCmsTenant();
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
    $this->assertDatabaseHas('cms_form_requests', [
        'tenant_id' => $tenant->id,
        'form' => 'contact',
        'form_type_id' => $formType->id,
    ]);
});

it('rejects a submission for an unknown form type', function (): void {
    $tenant = $this->createCmsTenant();
    NoerdUser::factory()->create(['api_token' => 'unknown_form_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    $this->withHeader('Authorization', 'Bearer unknown_form_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'does-not-exist',
            'data' => ['name' => 'Max'],
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('cms_form_requests', ['form' => 'does-not-exist']);
});

it('validates the submission against the form YAML and dispatches the email job', function (): void {
    Queue::fake();

    $tenant = $this->createCmsTenant();

    // FormType::loadYmlConfig() accepts an ABSOLUTE path, so the fixture lives in
    // the throwaway testing storage instead of the host's tracked app-configs.
    $fixtureDir = storage_path('framework/testing/zz-cms-form-api');
    File::deleteDirectory($fixtureDir);
    File::ensureDirectoryExists($fixtureDir);
    $ymlPath = $fixtureDir . '/contact.yml';
    File::put($ymlPath, implode("\n", [
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
    $this->assertDatabaseHas('cms_form_requests', [
        'id' => $response->json('id'),
        'data->email' => 'max@example.com',
    ]);
    $this->assertDatabaseMissing('cms_form_requests', [
        'id' => $response->json('id'),
        'data->undeclared' => 'dropped',
    ]);

    Queue::assertPushed(SendFormConfirmationEmail::class, 1);

    File::deleteDirectory($fixtureDir);
});

it('stores the form request under the tenant resolved for the token user', function (): void {
    // CmsApiAuth reads $user->selected_tenant_id, which on NoerdUser is a
    // session-backed ACCESSOR (TenantHelper::getSelectedTenantId()) — noerd_users
    // carries no column of its own and the persisted copy on the user's settings
    // row is only the starting point restored at login. The stored form request
    // must carry the tenant the middleware resolved, not a stale persisted value.
    $resolvedTenant = $this->createCmsTenant();
    $stalePersistedTenant = $this->createCmsTenant();
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
    $this->assertDatabaseHas('cms_form_requests', [
        'id' => $response->json('id'),
        'tenant_id' => $resolvedTenant->id,
    ]);
    $this->assertDatabaseMissing('cms_form_requests', [
        'id' => $response->json('id'),
        'tenant_id' => $stalePersistedTenant->id,
    ]);
});

it('rejects a form key that belongs to another tenant', function (): void {
    // The token resolves tenant A; the form key exists only for tenant B. The
    // controller must not reach across tenants — no row, no 201.
    $tenantA = $this->createCmsTenant();
    $tenantB = $this->createCmsTenant();

    $foreignFormType = FormType::factory()->create([
        'tenant_id' => $tenantB->id,
        'key' => 'foreign-contact',
    ]);

    NoerdUser::factory()->create(['api_token' => 'cross_tenant_token']);
    TenantHelper::setSelectedTenantId($tenantA->id);

    $this->withHeader('Authorization', 'Bearer cross_tenant_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'foreign-contact',
            'data' => ['name' => 'Mallory'],
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('cms_form_requests', ['form' => 'foreign-contact']);
    $this->assertDatabaseMissing('cms_form_requests', ['form_type_id' => $foreignFormType->id]);
});
