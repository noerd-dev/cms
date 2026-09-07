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
use Noerd\Models\TenantApp;

/**
 * The single tenant/user/language fixture of the CMS suite. Every test that
 * needs a tenant running the CMS app builds it here — never by hand.
 */
trait CreatesCmsUser
{
    /**
     * A tenant running the CMS app plus a user on it (optionally with a
     * profile); tenant and app are selected, the default language exists.
     */
    protected function withCmsModule(?Profile $profile = null): NoerdUser
    {
        $tenant = $this->createCmsTenant();

        $user = NoerdUser::factory()->create();
        $user->tenants()->attach($tenant->id, $profile ? ['profile_key' => $profile->value] : []);
        $user->update(['selected_tenant_id' => $tenant->id]);

        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('CMS');
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

        $cmsApp = TenantApp::firstOrCreate(
            ['name' => 'CMS'],
            [
                'title' => 'CMS',
                'icon' => 'heroicon:outline:rectangle-group',
                'route' => 'cms.dashboard',
                'is_active' => true,
            ],
        );
        $tenant->tenantApps()->syncWithoutDetaching([$cmsApp->id]);

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

        $language = CmsLanguage::updateOrCreate(
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
