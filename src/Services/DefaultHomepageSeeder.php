<?php

namespace Noerd\Cms\Services;

use Illuminate\Support\Facades\DB;

class DefaultHomepageSeeder
{
    /**
     * Create a default homepage page and cms_settings entry for every tenant that
     * does not yet have a homepage configured. Idempotent: tenants that already
     * have a homepage_page_id are skipped, so it is safe to run on every install.
     */
    public function seedMissingHomepages(): void
    {
        $tenantsWithoutHomepage = DB::table('tenants')
            ->whereNotIn('id', function ($query): void {
                $query->select('tenant_id')
                    ->from('cms_settings')
                    ->whereNotNull('homepage_page_id');
            })
            ->pluck('id');

        $now = now();

        foreach ($tenantsWithoutHomepage as $tenantId) {
            $pageId = DB::table('pages')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => json_encode(['de' => 'Startseite', 'en' => 'Homepage']),
                'slug' => json_encode(['de' => '/startseite', 'en' => '/homepage']),
                'is_active' => true,
                'layout' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('cms_settings')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'homepage_page_id' => $pageId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
