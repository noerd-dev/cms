<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds English as default language for all tenants that don't have any language.
     */
    public function up(): void
    {
        // Get all tenant IDs that don't have any language
        $tenantsWithoutLanguage = DB::table('tenants')
            ->whereNotIn('id', function ($query): void {
                $query->select('tenant_id')->from('cms_languages');
            })
            ->pluck('id');

        $now = now();

        foreach ($tenantsWithoutLanguage as $tenantId) {
            DB::table('cms_languages')->insert([
                'tenant_id' => $tenantId,
                'code' => 'en',
                'name' => 'English',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Also ensure all tenants have at least one default language
        $tenantsWithoutDefault = DB::table('cms_languages')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('SUM(is_default) = 0')
            ->pluck('tenant_id');

        foreach ($tenantsWithoutDefault as $tenantId) {
            $firstLanguage = DB::table('cms_languages')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if ($firstLanguage) {
                DB::table('cms_languages')
                    ->where('id', $firstLanguage->id)
                    ->update(['is_default' => true]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete languages on rollback - they may contain user data
    }
};
