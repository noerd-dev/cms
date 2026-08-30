<?php

namespace Noerd\Cms\Services;

use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
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
            $page = Page::create([
                'tenant_id' => $tenantId,
                'name' => ['de' => 'Startseite', 'en' => 'Homepage'],
                'slug' => ['de' => '/startseite', 'en' => '/homepage'],
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
