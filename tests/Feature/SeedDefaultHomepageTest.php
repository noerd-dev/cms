<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\DefaultHomepageSeeder;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);
uses(RefreshDatabase::class);

it('creates a default homepage for tenants without one', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    (new DefaultHomepageSeeder())->seedMissingHomepages();

    $this->assertDatabaseHas('pages', [
        'tenant_id' => $tenant->id,
        'name' => json_encode(['de' => 'Startseite', 'en' => 'Homepage']),
        'slug' => json_encode(['de' => '/startseite', 'en' => '/homepage']),
        'is_active' => true,
    ]);

    $setting = CmsSetting::where('tenant_id', $tenant->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->homepage_page_id)->not->toBeNull();

    $page = Page::find($setting->homepage_page_id);
    expect($page)->not->toBeNull();
    expect($page->tenant_id)->toBe($tenant->id);
});

it('does not overwrite an existing homepage', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    $existingPage = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => json_encode(['de' => 'Meine Seite', 'en' => 'My Page']),
    ]);

    CmsSetting::create([
        'tenant_id' => $tenant->id,
        'homepage_page_id' => $existingPage->id,
    ]);

    (new DefaultHomepageSeeder())->seedMissingHomepages();

    $setting = CmsSetting::where('tenant_id', $tenant->id)->first();
    expect($setting->homepage_page_id)->toBe($existingPage->id);

    // No additional page should have been created for this tenant
    expect(Page::where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('creates homepage for tenant with cms_settings but no homepage_page_id', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    CmsSetting::create([
        'tenant_id' => $tenant->id,
        'homepage_page_id' => null,
    ]);

    (new DefaultHomepageSeeder())->seedMissingHomepages();

    $setting = CmsSetting::where('tenant_id', $tenant->id)->first();
    expect($setting->homepage_page_id)->not->toBeNull();

    $page = Page::find($setting->homepage_page_id);
    expect($page)->not->toBeNull();
    expect($page->tenant_id)->toBe($tenant->id);
});
