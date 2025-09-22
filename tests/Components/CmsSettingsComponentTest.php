<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('loads and displays page options with localized names and saves selection', function (): void {
    $tenant = Tenant::factory()->create();

    // CMS App aktivieren, damit die Route/Seite verfügbar ist
    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms',
        'route' => 'cms.index',
    ]);
    $tenant->tenantApps()->attach($cmsApp->id);

    $profile = Profile::create([
        'key' => 'ADMIN',
        'name' => 'Administrator',
        'tenant_id' => $tenant->id,
    ]);

    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);
    $user->selected_tenant_id = $tenant->id;

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
        ->test('cms-settings-detail');

    // Erwartet: Lokalisierte Namen werden angezeigt
    $component->assertSee('de: Startseite en: Home Page');
    $component->assertSee('de: Kontakt en: Contact');

    // Auswahl treffen und speichern
    $component
        ->set('model.homepage_page_id', (string) $page2->id)
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
