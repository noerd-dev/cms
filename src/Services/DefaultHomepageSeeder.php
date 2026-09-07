<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Models\Tenant;

class DefaultHomepageSeeder
{
    /**
     * Create a default homepage page and cms_settings entry for every CMS tenant
     * that does not yet have a homepage configured. Idempotent: tenants that
     * already have a homepage_page_id are skipped, so it is safe to run on every
     * install and update.
     */
    public function seedMissingHomepages(): void
    {
        $tenantIdsWithHomepage = CmsSetting::query()
            ->whereNotNull('homepage_page_id')
            ->pluck('tenant_id');

        $tenantsWithoutHomepage = Tenant::query()
            ->whereHas('tenantApps', fn($query) => $query->where('name', 'CMS'))
            ->whereNotIn('id', $tenantIdsWithHomepage)
            ->pluck('id');

        foreach ($tenantsWithoutHomepage as $tenantId) {
            // One slot per language the tenant runs (the default language first,
            // without a language prefix) — never a hard-coded language list.
            $codes = CmsLanguage::forTenant((int) $tenantId)
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('sort_order')
                ->pluck('code')
                ->all() ?: CmsLanguageCodes::FALLBACK;

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

            CmsSetting::updateOrCreate(
                ['tenant_id' => $tenantId],
                ['homepage_page_id' => $page->id],
            );
        }
    }
}
