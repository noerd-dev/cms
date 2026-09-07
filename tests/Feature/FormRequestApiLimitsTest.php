<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\FormType;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $tenant = $this->createCmsTenant();
    FormType::factory()->create(['tenant_id' => $tenant->id, 'key' => 'contact']);
    NoerdUser::factory()->create(['api_token' => 'limits_token']);
    TenantHelper::setSelectedTenantId($tenant->id);
});

it('rejects a payload above the byte limit without storing a row', function (): void {
    // Multibyte on purpose: the limit counts bytes, not characters.
    $oversized = ['message' => str_repeat('ü', 40000)];

    $this->withHeader('Authorization', 'Bearer limits_token')
        ->postJson('/api/cms/form-requests', ['form' => 'contact', 'data' => $oversized])
        ->assertStatus(422)
        ->assertJson(['message' => 'Payload too large.']);

    $this->assertDatabaseCount('cms_form_requests', 0);
});

it('throttles the endpoint after 60 requests per minute', function (): void {
    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('Authorization', 'Bearer limits_token')
            ->postJson('/api/cms/form-requests', ['form' => 'contact', 'data' => ['name' => 'Max']])
            ->assertSuccessful();
    }

    $this->withHeader('Authorization', 'Bearer limits_token')
        ->postJson('/api/cms/form-requests', ['form' => 'contact', 'data' => ['name' => 'Max']])
        ->assertStatus(429);
});
