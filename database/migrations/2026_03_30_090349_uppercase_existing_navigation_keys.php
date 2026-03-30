<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('cms_navigations')
            ->whereRaw('navigation_key != UPPER(navigation_key)')
            ->update(['navigation_key' => DB::raw('UPPER(navigation_key)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
