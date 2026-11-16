<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Models\Tenant;
use Noerd\Support\TenantContext;

/**
 * The starter homepage of a FRESH CMS: one page plus the settings row naming it,
 * created by `noerd:install-cms` — and only for a tenant that has neither a
 * single page nor a settings row yet. A tenant that ever had a CMS (a page, a
 * settings row with or without homepage) is never touched again; in particular
 * `noerd:update-cms` creates no homepage, even when an admin cleared it.
 */
class DefaultHomepageSeeder
{
    /**
     * @return list<int> the tenants that got a starter homepage
     */
    public function seedMissingHomepages(): array
    {
        $seeded = [];

        $tenantIds = Tenant::query()
            ->whereHas('tenantApps', fn($query) => $query->where('name', 'CMS'))
            ->pluck('id');

        // Headless (install command): each tenant's homepage is written inside
        // its own tenant context.
        foreach ($tenantIds as $tenantId) {
            $tenantId = (int) $tenantId;

            if (TenantContext::run($tenantId, fn(): bool => $this->seedFreshTenant($tenantId))) {
                $seeded[] = $tenantId;
            }
        }

        return $seeded;
    }

    private function seedFreshTenant(int $tenantId): bool
    {
        if (CmsSetting::existsForTenant($tenantId) || Page::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return false;
        }

        // One slot per language the tenant runs (the default language first,
        // without a language prefix) — never a hard-coded language list.
        $codes = CmsLanguage::query()->ofTenant($tenantId)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->pluck('code')
            ->all() ?: [CmsLanguageCodes::defaultFor($tenantId)];

        $name = [];
        $slug = [];
        foreach ($codes as $index => $code) {
            $name[$code] = 'Homepage';
            $slug[$code] = $index === 0 ? '/homepage' : '/' . $code . '/homepage';
        }

        $page = Page::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'layout' => null,
        ]);

        CmsSetting::updateForTenant(['homepage_page_id' => $page->id], $tenantId);

        return true;
    }
}
