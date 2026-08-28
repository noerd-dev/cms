<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Profile;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('loads and displays page options with localized names and saves selection', function (): void {
    $tenant = Tenant::factory()->create();

    // CMS App aktivieren, damit die Route/Seite verfügbar ist
    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms::icons.app',
        'route' => 'cms.index',
    ]);
    $tenant->tenantApps()->attach($cmsApp->id);

    $profile = Profile::create([
        'key' => 'ADMIN',
        'name' => 'Administrator',
        'tenant_id' => $tenant->id,
    ]);

    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);
    TenantHelper::setSelectedTenantId($tenant->id);

    // Zwei Seiten mit lokalisierten Namen (als JSON gespeichert)
    $page1 = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => json_encode(['de' => 'Startseite', 'en' => 'Home Page'], JSON_UNESCAPED_UNICODE),
    ]);
    $page2 = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => json_encode(['de' => 'Kontakt', 'en' => 'Contact'], JSON_UNESCAPED_UNICODE),
    ]);

    // Komponente rendern
    $component = Livewire::actingAs($user)
        ->test('cms::settings-detail');

    // Erwartet: Lokalisierte Namen werden angezeigt
    $component->assertSee('de: Startseite en: Home Page');
    $component->assertSee('de: Kontakt en: Contact');

    // Auswahl treffen und speichern
    $component
        ->set('detailData.homepage_page_id', (string) $page2->id)
        ->call('store')
        ->assertDispatched('toast');

    // Persistenz prüfen
    $this->assertDatabaseHas('cms_settings', [
        'tenant_id' => $tenant->id,
        'homepage_page_id' => $page2->id,
    ]);

    // Model-Prüfung
    $setting = CmsSetting::where('tenant_id', $tenant->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->homepage_page_id)->toBe($page2->id);
});

it('saves comma separated form recipients and rejects invalid email addresses', function (): void {
    $tenant = Tenant::factory()->create();

    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms::icons.app',
        'route' => 'cms.index',
    ]);
    $tenant->tenantApps()->attach($cmsApp->id);

    $profile = Profile::create([
        'key' => 'ADMIN',
        'name' => 'Administrator',
        'tenant_id' => $tenant->id,
    ]);

    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);
    TenantHelper::setSelectedTenantId($tenant->id);

    $component = Livewire::actingAs($user)
        ->test('cms::settings-detail');

    $component
        ->set('detailData.form_recipients', 'one@example.com, two@example.com')
        ->call('store')
        ->assertHasNoErrors();

    expect(CmsSetting::formRecipientsForTenant($tenant->id))
        ->toBe(['one@example.com', 'two@example.com']);

    $component
        ->set('detailData.form_recipients', 'one@example.com, not-an-email')
        ->call('store')
        ->assertHasErrors('detailData.form_recipients');

    $component
        ->set('detailData.form_recipients', '')
        ->call('store')
        ->assertHasNoErrors();

    expect(CmsSetting::formRecipientsForTenant($tenant->id))->toBe([]);
});

it('saves the cookie consent duration and rejects values outside the allowed range', function (): void {
    $tenant = Tenant::factory()->create();

    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms::icons.app',
        'route' => 'cms.index',
    ]);
    $tenant->tenantApps()->attach($cmsApp->id);

    $profile = Profile::create([
        'key' => 'ADMIN',
        'name' => 'Administrator',
        'tenant_id' => $tenant->id,
    ]);

    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);
    TenantHelper::setSelectedTenantId($tenant->id);

    $component = Livewire::actingAs($user)
        ->test('cms::settings-detail');

    // Falls noch nichts gesetzt ist, greift der Default aus der Paket-Konfiguration
    $component->assertSet('detailData.cookie_lifetime_days', config('laravel-cookie-consent.cookie_lifetime'));

    $component
        ->set('detailData.show_cookie_banner', true)
        ->set('detailData.cookie_lifetime_days', 90)
        ->call('store')
        ->assertHasNoErrors();

    expect(CmsSetting::where('tenant_id', $tenant->id)->first()->cookieLifetimeInDays())->toBe(90);

    $component
        ->set('detailData.cookie_lifetime_days', 3650)
        ->call('store')
        ->assertHasErrors('detailData.cookie_lifetime_days');

    $component
        ->set('detailData.cookie_lifetime_days', 0)
        ->call('store')
        ->assertHasErrors('detailData.cookie_lifetime_days');
});
