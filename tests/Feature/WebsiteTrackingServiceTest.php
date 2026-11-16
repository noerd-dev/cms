<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Services\WebsiteTrackingService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Enums\Profile;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

/*
 | The ONE gate of the website's cookie banner and Google tag.
 */
beforeEach(function (): void {
    config(['app.url' => 'https://backend.test', 'session.domain' => null]);
    $this->tenant = Tenant::factory()->create();
    $this->service = app(WebsiteTrackingService::class);
});

it('shows nothing while the banner is off — the tag never runs without it', function (): void {
    CmsSetting::updateForTenant(['show_cookie_banner' => false, 'google_analytics_id' => 'G-ABCDE12'], $this->tenant->id);

    expect($this->service->forRequest($this->tenant->id, 'website.test'))
        ->toMatchArray(['showCookieBanner' => false, 'googleTagId' => null]);
});

it('hands out the banner with its lifetime and the tag on a website host', function (): void {
    CmsSetting::updateForTenant(['show_cookie_banner' => true, 'cookie_lifetime_days' => 30, 'google_analytics_id' => 'GTM-ABC1234'], $this->tenant->id);

    expect($this->service->forRequest($this->tenant->id, 'website.test'))
        ->toBe(['showCookieBanner' => true, 'cookieLifetimeDays' => 30, 'googleTagId' => 'GTM-ABC1234']);
});

it('never runs the tag on a host that shares the backend session', function (string $host, ?string $sessionDomain): void {
    config(['session.domain' => $sessionDomain]);
    CmsSetting::updateForTenant(['show_cookie_banner' => true, 'google_analytics_id' => 'G-ABCDE12'], $this->tenant->id);

    expect($this->service->forRequest($this->tenant->id, $host))
        ->toMatchArray(['showCookieBanner' => true, 'googleTagId' => null]);
})->with([
    'the backend host' => ['backend.test', null],
    'the backend host with www' => ['www.backend.test', null],
    'below the session cookie domain' => ['shop.example.test', '.example.test'],
]);

it('never hands out an id that breaks the pattern', function (): void {
    CmsSetting::updateForTenant(['show_cookie_banner' => true, 'google_analytics_id' => "G-1');alert(1)//"], $this->tenant->id);

    expect($this->service->forRequest($this->tenant->id, 'website.test')['googleTagId'])->toBeNull();
});

it('accepts only a Google tag id on the settings page', function (string $id, bool $valid): void {
    $this->actingAsCmsUser(Profile::Admin);

    $component = Livewire::test('cms::cms-settings-page')
        ->set('detailData.show_cookie_banner', true)
        ->set('detailData.google_analytics_id', $id)
        ->call('store');

    $valid ? $component->assertHasNoErrors() : $component->assertHasErrors(['detailData.google_analytics_id']);
})->with([
    'a Tag Manager container' => ['GTM-ABC1234', true],
    'a Google tag, lower case' => ['g-abcde12', true],
    'script' => ["G-1');alert(1)//", false],
    'an Analytics UA id' => ['UA-1234-1', false],
]);
