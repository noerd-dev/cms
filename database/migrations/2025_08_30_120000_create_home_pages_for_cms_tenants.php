<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $cmsAppId = DB::table('tenant_apps')->where('name', 'CMS')->value('id');

        if (!$cmsAppId) {
            return; // CMS app not present; nothing to do
        }

        $tenantIds = DB::table('tenant_app')
            ->where('tenant_app_id', $cmsAppId)
            ->pluck('tenant_id')
            ->all();

        if (empty($tenantIds)) {
            return;
        }

        foreach ($tenantIds as $tenantId) {
            $setting = DB::table('cms_settings')->where('tenant_id', $tenantId)->first();

            if ($setting && $setting->homepage_page_id) {
                // Homepage already set for this tenant; skip creating another page
                continue;
            }

            // Create a bare "Home" page for de/en
            $now = now();
            $pageId = DB::table('pages')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => json_encode(['de' => 'Home', 'en' => 'Home'], JSON_UNESCAPED_UNICODE),
                'slug' => json_encode(['de' => 'home', 'en' => 'home'], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'data' => null,
                'collection_id' => null,
                'sort' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Upsert cms_settings.homepage_page_id
            if ($setting) {
                DB::table('cms_settings')
                    ->where('tenant_id', $tenantId)
                    ->update(['homepage_page_id' => $pageId, 'updated_at' => $now]);
            } else {
                DB::table('cms_settings')->insert([
                    'tenant_id' => $tenantId,
                    'homepage_page_id' => $pageId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $cmsAppId = DB::table('tenant_apps')->where('name', 'CMS')->value('id');

        if (!$cmsAppId) {
            return;
        }

        $tenantIds = DB::table('tenant_app')
            ->where('tenant_app_id', $cmsAppId)
            ->pluck('tenant_id')
            ->all();

        if (empty($tenantIds)) {
            return;
        }

        foreach ($tenantIds as $tenantId) {
            $setting = DB::table('cms_settings')->where('tenant_id', $tenantId)->first();
            if (!$setting || !$setting->homepage_page_id) {
                continue;
            }

            $page = DB::table('pages')->where('id', $setting->homepage_page_id)->first();

            // Only revert if the page matches the default Home marker (de/en Home and slug home)
            $isDefaultHome = false;
            if ($page) {
                // Columns may store JSON as strings
                $name = json_decode((string) ($page->name ?? ''), true);
                $slug = json_decode((string) ($page->slug ?? ''), true);
                $isDefaultHome = is_array($name) && ($name['de'] ?? null) === 'Home' && ($name['en'] ?? null) === 'Home'
                    && is_array($slug) && ($slug['de'] ?? null) === 'home' && ($slug['en'] ?? null) === 'home';
            }

            // Clear homepage reference
            DB::table('cms_settings')->where('tenant_id', $tenantId)->update(['homepage_page_id' => null, 'updated_at' => now()]);

            if ($isDefaultHome) {
                DB::table('pages')->where('id', $page->id)->delete();
            }
        }
    }
};


