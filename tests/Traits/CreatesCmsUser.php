<?php

declare(strict_types=1);

namespace Noerd\Cms\Tests\Traits;

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

/**
 * The CMS-specific part of the suite's fixtures on top of the core's
 * createNoerdUserForApp(): the tenant's default language and the language
 * helpers. Every test that needs a tenant running the CMS app builds it here —
 * never by hand.
 */
trait CreatesCmsUser
{
    /**
     * The tenant_apps row of the CMS in its canonical shape.
     */
    private const array CMS_APP_ATTRIBUTES = [
        'title' => 'CMS',
        'icon' => 'heroicon:outline:rectangle-group',
        'route' => 'cms.dashboard',
    ];

    /**
     * createNoerdUserForApp('CMS') (a tenant running the CMS app plus a user on
     * it, optionally with a profile; tenant and app selected) with the tenant's
     * default language in place.
     */
    protected function withCmsModule(?Profile $profile = null): NoerdUser
    {
        $user = createNoerdUserForApp('CMS', profile: $profile, appAttributes: self::CMS_APP_ATTRIBUTES);

        // The Tenant::created hook seeds the default language; make sure it
        // exists even when the provider hook did not run.
        CmsLanguage::ensureDefaultLanguageForTenant((int) $user->selected_tenant_id);
        CmsLanguageCodes::clearCache();

        return $user;
    }

    /**
     * withCmsModule() + authenticated against the noerd guard — the standard
     * beforeEach fixture for component tests. Also exposes $this->user,
     * $this->tenant and $this->tenantId on the test case.
     */
    protected function actingAsCmsUser(?Profile $profile = null): NoerdUser
    {
        $user = $this->withCmsModule($profile);
        $tenant = $user->tenants()->firstOrFail();

        $this->actingAs($user, NoerdAuth::guardName());

        $this->user = $user;
        $this->tenant = $tenant;
        $this->tenantId = $tenant->id;

        return $user;
    }

    /**
     * A bare tenant running the CMS app — no user, nothing selected. For code
     * paths that carry no session (form sync, the token-authenticated API).
     */
    protected function createCmsTenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $tenant->assignApp(ensureTenantApp('CMS', self::CMS_APP_ATTRIBUTES));

        // The Tenant::created hook seeds the default language; make sure it
        // exists even when the provider hook did not run.
        CmsLanguage::ensureDefaultLanguageForTenant($tenant->id);

        return $tenant;
    }

    /**
     * Replace the tenant's languages with exactly one (the new default).
     */
    protected function useOnlyLanguage(string $code = 'de', string $name = 'Deutsch', ?int $tenantId = null): CmsLanguage
    {
        $tenantId ??= $this->tenantId ?? TenantHelper::getSelectedTenantId();

        CmsLanguage::withoutGlobalScopes()->where('tenant_id', $tenantId)->delete();

        $language = CmsLanguage::create([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
        ]);

        CmsLanguageCodes::clearCache();

        return $language;
    }

    /**
     * Add a further active language to the tenant.
     */
    protected function addLanguage(string $code, string $name, bool $default = false, ?int $tenantId = null): CmsLanguage
    {
        $tenantId ??= $this->tenantId ?? TenantHelper::getSelectedTenantId();

        $language = CmsLanguage::query()->ofTenant((int) $tenantId)->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => $code],
            ['name' => $name, 'is_active' => true, 'is_default' => $default],
        );

        CmsLanguageCodes::clearCache();

        return $language;
    }

    /**
     * @deprecated Use withCmsModule() / actingAsCmsUser(). Kept for the existing
     *             ['user' => …, 'tenant' => …] call sites.
     *
     * @return array{user: NoerdUser, tenant: Tenant}
     */
    protected function createUserWithCmsAccess(?Profile $profile = null): array
    {
        $user = $this->withCmsModule($profile);

        return ['user' => $user, 'tenant' => $user->tenants()->firstOrFail()];
    }
}
