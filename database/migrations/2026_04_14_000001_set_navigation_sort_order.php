<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Set sort_order on existing navigation entries that still have default 0.
     */
    public function up(): void
    {
        $navigationKeys = DB::table('cms_navigations')
            ->select('tenant_id', 'navigation_key')
            ->distinct()
            ->get();

        foreach ($navigationKeys as $group) {
            $entries = DB::table('cms_navigations')
                ->where('tenant_id', $group->tenant_id)
                ->where('navigation_key', $group->navigation_key)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id');

            foreach ($entries as $index => $id) {
                DB::table('cms_navigations')
                    ->where('id', $id)
                    ->update(['sort_order' => $index + 1]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
