<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\DefaultHomepageSeeder;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

it('creates a default homepage in the tenant\'s languages for tenants without one', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    // The tenant runs English (default, seeded) and Danish.
    $this->addLanguage('da', 'Dansk', tenantId: $tenant->id);

    (new DefaultHomepageSeeder())->seedMissingHomepages();

    $this->assertDatabaseHas('contents', [
        'tenant_id' => $tenant->id,
        'name->en' => 'Homepage',
        'name->da' => 'Homepage',
    ]);
    $this->assertDatabaseHas('cms_pages', [
        'tenant_id' => $tenant->id,
        'slug->en' => '/homepage',
        'slug->da' => '/da/homepage',
        'is_active' => true,
    ]);

    $setting = CmsSetting::where('tenant_id', $tenant->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->homepage_page_id)->not->toBeNull();

    $page = Page::query()->ofTenant($tenant->id)->find($setting->homepage_page_id);
    expect($page)->not->toBeNull();
    expect($page->tenant_id)->toBe($tenant->id);
});

it('does not overwrite an existing homepage', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    $existingPage = Page::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => ['de' => 'Meine Seite', 'en' => 'My Page'],
    ]);

    CmsSetting::create([
        'tenant_id' => $tenant->id,
        'homepage_page_id' => $existingPage->id,
    ]);

    (new DefaultHomepageSeeder())->seedMissingHomepages();

    $setting = CmsSetting::where('tenant_id', $tenant->id)->first();
    expect($setting->homepage_page_id)->toBe($existingPage->id);

    // No additional page should have been created for this tenant
    expect(Page::query()->ofTenant($tenant->id)->count())->toBe(1);
});

it('never seeds a tenant that has a settings row, even one without homepage', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    // The admin cleared the homepage: the CMS respects it.
    CmsSetting::create(['tenant_id' => $tenant->id, 'homepage_page_id' => null]);

    expect((new DefaultHomepageSeeder())->seedMissingHomepages())->toBe([]);

    expect(CmsSetting::where('tenant_id', $tenant->id)->value('homepage_page_id'))->toBeNull()
        ->and(Page::query()->ofTenant($tenant->id)->count())->toBe(0);
});

it('never seeds a tenant that already has a page', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();
    Page::factory()->create(['tenant_id' => $tenant->id]);

    (new DefaultHomepageSeeder())->seedMissingHomepages();

    expect(CmsSetting::existsForTenant($tenant->id))->toBeFalse()
        ->and(Page::query()->ofTenant($tenant->id)->count())->toBe(1);
});

it('seeds a fresh tenant once — a second run adds nothing', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

    expect((new DefaultHomepageSeeder())->seedMissingHomepages())->toBe([$tenant->id])
        ->and((new DefaultHomepageSeeder())->seedMissingHomepages())->toBe([]);

    expect(Page::query()->ofTenant($tenant->id)->count())->toBe(1);
});
