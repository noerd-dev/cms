<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Support\ComponentAccessGuard;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

it('redirects guests to the login', function (): void {
    $this->get('/cms/pages')->assertRedirect(route('noerd.login'));
});

it('rejects a tenant without the CMS app', function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    $user->update(['selected_tenant_id' => $tenant->id]);

    // The CMS app exists but is NOT assigned to this tenant.
    TenantApp::firstOrCreate(
        ['name' => 'CMS'],
        ['title' => 'CMS', 'icon' => 'heroicon:outline:rectangle-group', 'route' => 'cms.dashboard', 'is_active' => true],
    );

    $this->actingAs($user, NoerdAuth::guardName());

    $this->get('/cms/pages')->assertStatus(403);
});

it('lets a tenant with the CMS app open the pages list and the dashboard', function (): void {
    $this->actingAsCmsUser();

    $this->get('/cms/pages')
        ->assertOk()
        ->assertSeeLivewire('cms::pages-list');

    $this->get('/cms')
        ->assertOk()
        ->assertSeeLivewire('cms::dashboard');
});

it('keeps the tenant-wide configuration screens admin-only', function (string $uri, string $component): void {
    $this->actingAsCmsUser(Profile::User);

    $this->get($uri)->assertStatus(403);
    expect(ComponentAccessGuard::allows($component))->toBeFalse();

    // The generic component page is one of the dynamic-mount seams the guard closes.
    $this->get('/noerd/component-page/' . $component)->assertStatus(403);
})->with([
    'settings' => ['/cms/settings', 'cms::settings-page'],
    'languages' => ['/cms/languages', 'cms::languages-list'],
    'collection definitions' => ['/cms/collection-definitions', 'cms::collection-definitions-list'],
]);

it('opens the configuration screens for an admin', function (string $uri, string $component): void {
    $this->actingAsCmsUser(Profile::Admin);

    $this->get($uri)
        ->assertOk()
        ->assertSeeLivewire($component);
    expect(ComponentAccessGuard::allows($component))->toBeTrue();
})->with([
    'settings' => ['/cms/settings', 'cms::settings-page'],
    'languages' => ['/cms/languages', 'cms::languages-list'],
    'collection definitions' => ['/cms/collection-definitions', 'cms::collection-definitions-list'],
]);

it('leaves the content screens open to a regular user', function (): void {
    $this->actingAsCmsUser(Profile::User);

    $this->get('/cms/form-types')
        ->assertOk()
        ->assertSeeLivewire('cms::form-types-list');
});
