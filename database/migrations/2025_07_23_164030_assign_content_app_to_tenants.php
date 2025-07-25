<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Noerd\Noerd\Models\Tenant;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the CMS/Content app
        $cmsApp = DB::table('tenant_apps')->where('name', 'CMS')->first();

        if (!$cmsApp) {
            // Create the CMS app if it doesn't exist
            $cmsAppId = DB::table('tenant_apps')->insertGetId([
                'title' => 'CMS',
                'name' => 'CMS',
                'icon' => 'icons.planning',
                'route' => 'cms.pages',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $cmsAppId = $cmsApp->id;
        }

        // Get all tenants
        $tenants = Tenant::all();
        $assignedCount = 0;
        $skippedCount = 0;

        foreach ($tenants as $tenant) {
            // Check if tenant already has access to CMS app
            $existingAccess = DB::table('tenant_app')
                ->where('tenant_id', $tenant->id)
                ->where('tenant_app_id', $cmsAppId)
                ->exists();

            if (!$existingAccess) {
                // Assign CMS app to tenant
                DB::table('tenant_app')->insert([
                    'tenant_app_id' => $cmsAppId,
                    'tenant_id' => $tenant->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $assignedCount++;
            } else {
                $skippedCount++;
            }
        }

        // Log the results (visible in migration output)
        echo "Content/CMS App Assignment Summary:\n";
        echo "- CMS App ID: {$cmsAppId}\n";
        echo "- Total tenants: " . $tenants->count() . "\n";
        echo "- Tenants assigned CMS access: {$assignedCount}\n";
        echo "- Tenants skipped (already had access): {$skippedCount}\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find the CMS app
        $cmsApp = DB::table('tenant_apps')->where('name', 'CMS')->first();

        if ($cmsApp) {
            // Remove all tenant assignments for the CMS app
            $removedCount = DB::table('tenant_app')
                ->where('tenant_app_id', $cmsApp->id)
                ->delete();

            echo "Removed CMS app access from {$removedCount} tenants.\n";
        }
    }
};
