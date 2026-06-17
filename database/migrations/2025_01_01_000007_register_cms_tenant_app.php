<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Register the CMS app in tenant_apps so a plain `php artisan migrate` also
     * makes it available. Idempotent and complementary to the install command's
     * published stub (app-configs/stubs/add_cms_tenant_app.php.stub). Tenant
     * assignment is handled interactively by noerd:install-cms, not here.
     */
    public function up(): void
    {
        if (! DB::table('tenant_apps')->where('name', 'CMS')->exists()) {
            DB::table('tenant_apps')->insert([
                'title' => 'CMS',
                'name' => 'CMS',
                'icon' => 'cms::icons.app',
                'route' => 'cms.dashboard',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tenant_apps')->where('name', 'CMS')->delete();
    }
};
