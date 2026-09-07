<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Enums\Profile;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->user = $this->withCmsModule(Profile::Admin);
    $this->tenant = $this->user->tenants()->firstOrFail();
});

it('loads and displays page options with localized names and saves selection', function (): void {
    $user = $this->user;
    $tenant = $this->tenant;

    $page1 = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => ['de' => 'Startseite', 'en' => 'Home Page'],
    ]);
    $page2 = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => ['de' => 'Kontakt', 'en' => 'Contact'],
    ]);

    $component = Livewire::actingAs($user)
        ->test('cms::settings-page');

    // Page options render the localized display name of every page
    $component->assertSee(\Noerd\Support\RelationFieldDefinition::normalizeDisplayValue($page1->name));
    $component->assertSee(\Noerd\Support\RelationFieldDefinition::normalizeDisplayValue($page2->name));

    // Select and save
    $component
        ->set('detailData.homepage_page_id', (string) $page2->id)
        ->call('store')
        ->assertDispatched('toast');

    $this->assertDatabaseHas('cms_settings', [
        'tenant_id' => $tenant->id,
        'homepage_page_id' => $page2->id,
    ]);

    $setting = CmsSetting::where('tenant_id', $tenant->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->homepage_page_id)->toBe($page2->id);
});

it('does not create a settings row as a render side effect', function (): void {
    $user = $this->user;
    $tenant = $this->tenant;

    Livewire::actingAs($user)->test('cms::settings-page');

    // The tenant singleton is created lazily on the first save.
    expect(CmsSetting::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

it('rejects a homepage page belonging to another tenant', function (): void {
    $user = $this->user;

    $foreignTenant = Tenant::factory()->create();
    $foreignPage = Page::factory()->create(['tenant_id' => $foreignTenant->id]);

    Livewire::actingAs($user)
        ->test('cms::settings-page')
        ->set('detailData.homepage_page_id', (string) $foreignPage->id)
        ->call('store')
        ->assertHasErrors('detailData.homepage_page_id');
});

it('saves comma separated form recipients and rejects invalid email addresses', function (): void {
    $user = $this->user;
    $tenant = $this->tenant;

    $component = Livewire::actingAs($user)
        ->test('cms::settings-page');

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
    $user = $this->user;
    $tenant = $this->tenant;

    // Without a stored value the form shows the effective default: the
    // cookie-consent package configuration, then the built-in default.
    $expectedDefault = ((int) config('laravel-cookie-consent.cookie_lifetime'))
        ?: CmsSetting::DEFAULT_COOKIE_LIFETIME_DAYS;

    $component = Livewire::actingAs($user)
        ->test('cms::settings-page');

    $component->assertSet('detailData.cookie_lifetime_days', $expectedDefault);

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
