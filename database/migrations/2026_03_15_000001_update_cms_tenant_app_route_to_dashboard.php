<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('tenant_apps')
            ->where('name', 'CMS')
            ->update(['route' => 'cms.dashboard']);
    }

    public function down(): void
    {
        DB::table('tenant_apps')
            ->where('name', 'CMS')
            ->update(['route' => 'cms.pages']);
    }
};
