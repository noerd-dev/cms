<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     * Creates a default homepage page and cms_settings entry for all tenants
     * that don't have a homepage configured yet.
     */
    public function up(): void
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete pages on rollback - they may contain user data
    }
};
