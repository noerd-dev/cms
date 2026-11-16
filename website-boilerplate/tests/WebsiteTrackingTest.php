<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Website\Models\Page;
use Noerd\Website\Models\Tenant;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/*
 | The cookie banner and the Google tag of the website layout — gated by the
 | CMS's WebsiteTrackingService: banner when switched on, the tag only with the
 | banner, loaded after consent, and only on a host apart from the backend's.
 */
beforeEach(function (): void {
    config(['app.url' => 'https://backend.test', 'session.domain' => null]);
    $this->tenant = Tenant::factory()->create();
    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => ['de' => 'Startseite', 'en' => 'Homepage'],
        'slug' => ['de' => '/', 'en' => '/'],
        'is_active' => true,
    ]);
    $this->settings = CmsSetting::factory()->create(['tenant_id' => $this->tenant->id, 'homepage_page_id' => $page->id]);
});

function zzWebsiteHome(object $test, string $host = 'www.website.test'): \Illuminate\Testing\TestResponse
{
    return $test->get('https://' . $host . '/index?uuid=' . $test->tenant->uuid);
}

it('renders neither banner nor tag while the banner is switched off', function (): void {
    $this->settings->update(['show_cookie_banner' => false, 'google_analytics_id' => 'GTM-ABCD123']);

    zzWebsiteHome($this)->assertOk()
        ->assertDontSee('loadWebsiteGoogleTag', false)
        ->assertDontSee('GTM-ABCD123', false);
});

it('renders the banner and the consent-bound tag loader on the website host', function (): void {
    $this->settings->update(['show_cookie_banner' => true, 'cookie_lifetime_days' => 90, 'google_analytics_id' => 'GTM-ABCD123']);

    zzWebsiteHome($this)->assertOk()
        ->assertSee('window.loadWebsiteGoogleTag', false)
        ->assertSee('GTM-ABCD123', false)
        // The tag is loaded by the banner after consent — never by a plain tag.
        ->assertDontSee('googletagmanager.com/gtm.js?id=GTM-ABCD123', false);
});

it('never runs the tag on the backend host', function (): void {
    $this->settings->update(['show_cookie_banner' => true, 'google_analytics_id' => 'GTM-ABCD123']);

    zzWebsiteHome($this, 'backend.test')->assertOk()
        ->assertDontSee('loadWebsiteGoogleTag', false);
});

it('never renders an id that breaks the pattern', function (): void {
    $this->settings->update(['show_cookie_banner' => true, 'google_analytics_id' => "G-1');alert(1);//"]);

    zzWebsiteHome($this)->assertOk()
        ->assertDontSee('loadWebsiteGoogleTag', false)
        ->assertDontSee('alert(1)', false);
});
