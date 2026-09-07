<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

it('ensures the default language of ANOTHER tenant while a user is signed in', function (): void {
    $this->actingAsCmsUser();

    // Tenant::created already seeded the other tenant's default language; the
    // explicit call must find it through the acting user's tenant scope …
    $other = Tenant::factory()->create();

    $first = CmsLanguage::ensureDefaultLanguageForTenant($other->id);
    // … and a second call must not attempt a duplicate insert.
    $second = CmsLanguage::ensureDefaultLanguageForTenant($other->id);

    expect($first->id)->toBe($second->id)
        ->and(CmsLanguage::forTenant($other->id)->count())->toBe(1);
});

it('keeps exactly one default language per tenant when saving for a foreign tenant', function (): void {
    $this->actingAsCmsUser();
    $other = Tenant::factory()->create();

    CmsLanguage::create(['tenant_id' => $other->id, 'code' => 'fr', 'name' => 'Français', 'is_active' => true, 'is_default' => true]);

    expect(CmsLanguage::forTenant($other->id)->where('is_default', true)->count())->toBe(1)
        ->and(CmsLanguage::forTenant($other->id)->where('is_default', true)->value('code'))->toBe('fr');
});

it('never answers with the union of every tenant\'s codes outside a tenant context', function (): void {
    $tenantA = $this->createCmsTenant();
    $tenantB = $this->createCmsTenant();
    CmsLanguage::create(['tenant_id' => $tenantA->id, 'code' => 'da', 'name' => 'Dansk', 'is_active' => true]);
    CmsLanguage::create(['tenant_id' => $tenantB->id, 'code' => 'sv', 'name' => 'Svenska', 'is_active' => true]);
    CmsLanguageCodes::clearCache();

    // Console / queue: no user, no session — the fallback, not da+sv+en.
    session()->forget('noerd.selected_tenant_id');
    expect(CmsLanguageCodes::active())->toBe(CmsLanguageCodes::FALLBACK);

    // A selected tenant in the session is enough to resolve that tenant's codes.
    TenantHelper::setSelectedTenantId($tenantA->id);
    CmsLanguageCodes::clearCache();
    expect(CmsLanguageCodes::active())->toBe(['en', 'da']);
});
