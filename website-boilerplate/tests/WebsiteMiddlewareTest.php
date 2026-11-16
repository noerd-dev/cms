<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Noerd\Website\Middleware\WebsiteMiddleware;
use Noerd\Website\Models\Tenant;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * The probe route is registered per test at runtime. It is a POST route on
 * purpose: the module's own GET catch-all ({slug?}/{slug2?}/{slug3?}) is
 * registered during boot and would shadow any GET route registered later
 * from a test. POST also keeps the 'web' group (and its CSRF check) out of
 * the way — the array session driver works without StartSession in tests.
 */
function registerWebsiteMiddlewareProbeRoute(array &$captured): void
{
    Route::post('/_test/website-middleware', function (Request $request) use (&$captured) {
        $captured = [
            'tenant_id' => $request->attributes->get('tenant_id'),
            'tenant' => $request->attributes->get('tenant'),
        ];

        return response()->json(['ok' => true]);
    })->middleware(WebsiteMiddleware::class);
}

it('resolves the tenant from the uuid and primes the session', function (): void {
    Tenant::factory()->create(); // decoy first tenant

    $tenant = Tenant::factory()->create();

    $captured = [];
    registerWebsiteMiddlewareProbeRoute($captured);

    $response = $this->post('/_test/website-middleware?uuid=' . $tenant->uuid);

    $response->assertOk();
    expect($captured['tenant_id'])->toBe($tenant->id);
    expect($captured['tenant'])->toBeInstanceOf(Tenant::class);
    expect($captured['tenant']->id)->toBe($tenant->id);

    $response->assertSessionHas('selectedTenantId', $tenant->id);
    $response->assertSessionHas('uuid', $tenant->uuid);
});

it('reuses the uuid already stored in the session when the request carries none', function (): void {
    Tenant::factory()->create(); // would win via the first-tenant fallback

    $tenant = Tenant::factory()->create();

    $captured = [];
    registerWebsiteMiddlewareProbeRoute($captured);

    $response = $this->withSession(['uuid' => $tenant->uuid])
        ->post('/_test/website-middleware');

    $response->assertOk();
    expect($captured['tenant_id'])->toBe($tenant->id);
    $response->assertSessionHas('selectedTenantId', $tenant->id);
    $response->assertSessionHas('uuid', $tenant->uuid);
});

it('lets a request uuid override the session uuid', function (): void {
    $sessionTenant = Tenant::factory()->create();
    $requestTenant = Tenant::factory()->create();

    $captured = [];
    registerWebsiteMiddlewareProbeRoute($captured);

    $response = $this->withSession(['uuid' => $sessionTenant->uuid])
        ->post('/_test/website-middleware?uuid=' . $requestTenant->uuid);

    $response->assertOk();
    expect($captured['tenant_id'])->toBe($requestTenant->id);
    $response->assertSessionHas('selectedTenantId', $requestTenant->id);
    $response->assertSessionHas('uuid', $requestTenant->uuid);
});

it('falls back to the first tenant for an unknown uuid', function (): void {
    $firstTenant = Tenant::factory()->create();
    Tenant::factory()->create();

    $captured = [];
    registerWebsiteMiddlewareProbeRoute($captured);

    $response = $this->post('/_test/website-middleware?uuid=does-not-exist');

    $response->assertOk();
    expect($captured['tenant_id'])->toBe($firstTenant->id);
    $response->assertSessionHas('selectedTenantId', $firstTenant->id);
    // The unknown uuid is NOT persisted — only a resolved uuid is stored.
    $response->assertSessionMissing('uuid');
});

it('aborts with 404 when no tenant exists at all', function (): void {
    $captured = [];
    registerWebsiteMiddlewareProbeRoute($captured);

    $response = $this->post('/_test/website-middleware?uuid=does-not-exist');

    $response->assertNotFound();
    expect($captured)->toBe([]);
});

it('works in the resolved tenant for every tenant-scoped query', function (): void {
    Tenant::factory()->create(); // decoy first tenant
    $tenant = Tenant::factory()->create();

    $seen = null;
    Route::post('/_test/website-tenant-context', function () use (&$seen) {
        $seen = \Noerd\Support\TenantContext::current();

        return response()->json(['ok' => true]);
    })->middleware(WebsiteMiddleware::class);

    $this->post('/_test/website-tenant-context?uuid=' . $tenant->uuid)->assertOk();

    expect($seen)->toBe($tenant->id);
});
