<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Noerd\Cms\Jobs\SendFormConfirmationEmail;
use Noerd\Cms\Jobs\SendFormNotificationEmail;
use Noerd\Cms\Models\FormType;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesFormFixtures;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesFormFixtures::class);

it('stores a form request via API using user api token', function (): void {
    // Arrange: create a tenant, its form type and a user with api token
    $tenant = $this->createCmsTenant();
    $formType = $this->formTypeWithYaml($tenant->id);
    $user = NoerdUser::factory()->create([
        'api_token' => 'test_token_123',
    ]);
    $this->selectTenantForToken($user, $tenant);

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
    $user = NoerdUser::factory()->create(['api_token' => 'unknown_form_token']);
    $this->selectTenantForToken($user, $tenant);

    $this->withHeader('Authorization', 'Bearer unknown_form_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'does-not-exist',
            'data' => ['name' => 'Max'],
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('cms_form_requests', ['form' => 'does-not-exist']);
});

it('validates the submission against the form YAML and dispatches the mail jobs', function (): void {
    Queue::fake();

    $tenant = $this->createCmsTenant();
    $this->formTypeWithYaml($tenant->id, attributes: ['send_email' => true, 'send_confirmation' => true], yaml: implode("\n", [
        'key: contact',
        'title: Contact',
        'fields:',
        '  - name: email',
        '    label: Email',
        '    required: true',
        '    validation:',
        '      - email',
    ]));
    $user = NoerdUser::factory()->create(['api_token' => 'yaml_validation_token']);
    $this->selectTenantForToken($user, $tenant);

    // Invalid per the YAML rules: `required: true` alone makes the field required.
    $this->withHeader('Authorization', 'Bearer yaml_validation_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'contact',
            'data' => ['name' => 'Max', 'undeclared' => 'dropped'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email', 'errors');

    Queue::assertNothingPushed();

    // Valid submission: stored (only declared fields) and both mails queued
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

    Queue::assertPushed(SendFormNotificationEmail::class, 1);
    Queue::assertPushed(SendFormConfirmationEmail::class, 1);
});

it('queues only the mails a form type sends', function (): void {
    Queue::fake();

    $tenant = $this->createCmsTenant();
    $this->formTypeWithYaml($tenant->id, attributes: ['send_email' => true, 'send_confirmation' => false]);
    $user = NoerdUser::factory()->create(['api_token' => 'only_notification_token']);
    $this->selectTenantForToken($user, $tenant);

    $this->withHeader('Authorization', 'Bearer only_notification_token')
        ->postJson('/api/cms/form-requests', ['form' => 'contact', 'data' => ['email' => 'max@example.com']])
        ->assertCreated();

    Queue::assertPushed(SendFormNotificationEmail::class, 1);
    Queue::assertNotPushed(SendFormConfirmationEmail::class);
});

it('refuses a submission when the form definition is missing (fail-closed)', function (): void {
    Queue::fake();

    $tenant = $this->createCmsTenant();
    // The factory's yml_path resolves to no file.
    FormType::factory()->create(['tenant_id' => $tenant->id, 'key' => 'contact', 'send_email' => true]);
    $user = NoerdUser::factory()->create(['api_token' => 'missing_yaml_token']);
    $this->selectTenantForToken($user, $tenant);

    $this->withHeader('Authorization', 'Bearer missing_yaml_token')
        ->postJson('/api/cms/form-requests', ['form' => 'contact', 'data' => ['email' => 'max@example.com']])
        ->assertStatus(422)
        ->assertJson(['message' => 'The form definition is missing.']);

    $this->assertDatabaseCount('cms_form_requests', 0);
    Queue::assertNothingPushed();
});

it('refuses a submission for an inactive form type', function (): void {
    $tenant = $this->createCmsTenant();
    $this->formTypeWithYaml($tenant->id, attributes: ['is_active' => false]);
    $user = NoerdUser::factory()->create(['api_token' => 'inactive_form_token']);
    $this->selectTenantForToken($user, $tenant);

    $this->withHeader('Authorization', 'Bearer inactive_form_token')
        ->postJson('/api/cms/form-requests', ['form' => 'contact', 'data' => ['email' => 'max@example.com']])
        ->assertStatus(422)
        ->assertJson(['message' => 'Unknown form type.']);

    $this->assertDatabaseCount('cms_form_requests', 0);
});

it('stores the form request under the tenant the token user persisted', function (): void {
    // Nobody is signed in on a token request: CmsApiAuth reads the token user's
    // PERSISTED selection (the settings row), never a session tenant of the
    // request — a session belongs to the signed-in user only.
    $persistedTenant = $this->createCmsTenant();
    $sessionTenant = $this->createCmsTenant();
    $this->formTypeWithYaml($persistedTenant->id);
    $this->formTypeWithYaml($sessionTenant->id);

    $user = NoerdUser::factory()->create(['api_token' => 'tenant_precedence_token']);
    $this->selectTenantForToken($user, $persistedTenant);

    // A session selection of nobody in particular must not decide the tenant.
    TenantHelper::setSelectedTenantId($sessionTenant->id);

    $response = $this->withHeader('Authorization', 'Bearer tenant_precedence_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'contact',
            'data' => ['name' => 'Jane Doe'],
        ]);

    $response->assertCreated();
    $this->assertDatabaseHas('cms_form_requests', [
        'id' => $response->json('id'),
        'tenant_id' => $persistedTenant->id,
    ]);
    $this->assertDatabaseMissing('cms_form_requests', [
        'id' => $response->json('id'),
        'tenant_id' => $sessionTenant->id,
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

    $user = NoerdUser::factory()->create(['api_token' => 'cross_tenant_token']);
    $this->selectTenantForToken($user, $tenantA);

    $this->withHeader('Authorization', 'Bearer cross_tenant_token')
        ->postJson('/api/cms/form-requests', [
            'form' => 'foreign-contact',
            'data' => ['name' => 'Mallory'],
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('cms_form_requests', ['form' => 'foreign-contact']);
    $this->assertDatabaseMissing('cms_form_requests', ['form_type_id' => $foreignFormType->id]);
});

it('refuses the token of a user removed from the tenant', function (): void {
    $tenant = $this->createCmsTenant();
    FormType::factory()->create(['tenant_id' => $tenant->id, 'key' => 'contact']);
    $user = NoerdUser::factory()->create(['api_token' => 'removed_member_token']);
    $this->selectTenantForToken($user, $tenant);

    $tenant->removeMember($user);

    $this->withHeader('Authorization', 'Bearer removed_member_token')
        ->postJson('/api/cms/form-requests', ['form' => 'contact', 'data' => ['name' => 'Max']])
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthorized']);

    $this->assertDatabaseMissing('cms_form_requests', ['tenant_id' => $tenant->id]);
});

it('refuses a stored selection of a tenant the user is no member of', function (): void {
    $tenant = $this->createCmsTenant();
    FormType::factory()->create(['tenant_id' => $tenant->id, 'key' => 'contact']);
    $user = NoerdUser::factory()->create(['api_token' => 'stale_selection_token']);
    $user->setting->update(['selected_tenant_id' => $tenant->id]);

    $this->withHeader('Authorization', 'Bearer stale_selection_token')
        ->postJson('/api/cms/form-requests', ['form' => 'contact', 'data' => ['name' => 'Max']])
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthorized']);

    $this->assertDatabaseMissing('cms_form_requests', ['tenant_id' => $tenant->id]);
});
