<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Insert CMS tenant app entry if it doesn't exist (auto-increment ID)
            DB::statement("
                INSERT INTO `tenant_apps` (`title`, `name`, `icon`, `route`, `is_active`, `created_at`, `updated_at`) 
                SELECT 'CMS', 'CMS', 'icons.planning', 'cms.pages', 1, NOW(), NOW()
                WHERE NOT EXISTS (
                    SELECT 1 FROM `tenant_apps` WHERE `name` = 'CMS'
                )
            ");
        } catch (\Exception $e) {
            // Log the error but don't fail the migration if the entry already exists
            Log::info('CMS tenant app entry might already exist: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Remove the CMS tenant app entry by name
            DB::table('tenant_apps')->where('name', 'CMS')->delete();
        } catch (\Exception $e) {
            Log::info('Could not remove CMS tenant app entry: ' . $e->getMessage());
        }
    }
};
