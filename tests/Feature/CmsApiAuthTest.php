<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

/**
 * The probe route is registered per test at runtime. It is a POST route on
 * purpose: the website module registers a GET catch-all ({slug?}/{slug2?}/{slug3?})
 * during boot, which would shadow any GET route registered later from a test.
 */
function zzRegisterCmsApiAuthProbeRoute(array &$captured): void
{
    Route::post('/_test/cms-api-auth', function (Request $request) use (&$captured) {
        $captured = [
            'tenant_id' => $request->attributes->get('tenant_id'),
            'tenant' => $request->attributes->get('tenant'),
            'user' => $request->attributes->get('user'),
        ];

        return response()->json(['ok' => true]);
    })->middleware('cms_api');
}

it('authenticates the same user via bearer header and x-api-key header', function (): void {
    $tenant = $this->createCmsTenant();
    $user = NoerdUser::factory()->create(['api_token' => 'multi_channel_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    $captured = [];
    zzRegisterCmsApiAuthProbeRoute($captured);

    // Authorization: Bearer <token>
    $this->withHeader('Authorization', 'Bearer multi_channel_token')
        ->postJson('/_test/cms-api-auth')
        ->assertOk();
    expect($captured['user']->id)->toBe($user->id);

    // X-API-Key: <token> — flush the sticky default headers first, so the
    // Bearer header of the previous request cannot leak into this one.
    $captured = [];
    $this->flushHeaders()
        ->withHeader('X-API-Key', 'multi_channel_token')
        ->postJson('/_test/cms-api-auth')
        ->assertOk();
    expect($captured['user']->id)->toBe($user->id);

});

it('never reads the token from the query string', function (): void {
    $tenant = $this->createCmsTenant();
    NoerdUser::factory()->create(['api_token' => 'query_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    $captured = [];
    zzRegisterCmsApiAuthProbeRoute($captured);

    // Query parameters land in access logs and Referer headers — not a channel.
    $this->postJson('/_test/cms-api-auth?api_token=query_token')
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthorized']);
    expect($captured)->toBe([]);
});

it('rejects a token whose tenant does not run the CMS app', function (): void {
    $tenant = Tenant::factory()->create();
    NoerdUser::factory()->create(['api_token' => 'crm_only_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    $captured = [];
    zzRegisterCmsApiAuthProbeRoute($captured);

    $this->withHeader('Authorization', 'Bearer crm_only_token')
        ->postJson('/_test/cms-api-auth')
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthorized']);
    expect($captured)->toBe([]);
});

it('rejects an unknown token with 401', function (): void {
    $tenant = $this->createCmsTenant();
    NoerdUser::factory()->create(['api_token' => 'known_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    $captured = [];
    zzRegisterCmsApiAuthProbeRoute($captured);

    $this->withHeader('Authorization', 'Bearer unknown_token')
        ->postJson('/_test/cms-api-auth')
        ->assertStatus(401);

    expect($captured)->toBe([]);
});

it('rejects a request without any token with 401', function (): void {
    $captured = [];
    zzRegisterCmsApiAuthProbeRoute($captured);

    $this->postJson('/_test/cms-api-auth')->assertStatus(401);

    expect($captured)->toBe([]);
});

it('rejects a valid token when no tenant is selected for the user', function (): void {
    // selected_tenant_id on NoerdUser is a session-backed accessor
    // (TenantHelper::getSelectedTenantId()) — there is no column behind it. With
    // an empty session and no tenant rows at all, it resolves to null and the
    // middleware must refuse.
    NoerdUser::factory()->create(['api_token' => 'tenantless_token']);

    $captured = [];
    zzRegisterCmsApiAuthProbeRoute($captured);

    $this->withHeader('Authorization', 'Bearer tenantless_token')
        ->postJson('/_test/cms-api-auth')
        ->assertStatus(401);

    expect($captured)->toBe([]);
});

it('rejects a valid token when the selected tenant row no longer exists', function (): void {
    $tenant = $this->createCmsTenant();
    NoerdUser::factory()->create(['api_token' => 'stale_tenant_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    $tenant->delete();
    TenantHelper::clearCache();

    $captured = [];
    zzRegisterCmsApiAuthProbeRoute($captured);

    $this->withHeader('Authorization', 'Bearer stale_tenant_token')
        ->postJson('/_test/cms-api-auth')
        ->assertStatus(401);

    expect($captured)->toBe([]);
});

it('attaches tenant_id, tenant and user to the request on success', function (): void {
    $tenant = $this->createCmsTenant();
    $user = NoerdUser::factory()->create(['api_token' => 'attribute_probe_token']);
    TenantHelper::setSelectedTenantId($tenant->id);

    $captured = [];
    zzRegisterCmsApiAuthProbeRoute($captured);

    $this->withHeader('Authorization', 'Bearer attribute_probe_token')
        ->postJson('/_test/cms-api-auth')
        ->assertOk();

    expect($captured['tenant_id'])->toBe($tenant->id);
    expect($captured['tenant'])->toBeInstanceOf(Tenant::class);
    expect($captured['tenant']->id)->toBe($tenant->id);
    expect($captured['user'])->toBeInstanceOf(NoerdUser::class);
    expect($captured['user']->id)->toBe($user->id);
});
