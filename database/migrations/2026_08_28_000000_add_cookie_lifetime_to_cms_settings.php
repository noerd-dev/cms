<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('cms_settings', 'cookie_lifetime_days')) {
            Schema::table('cms_settings', function (Blueprint $table): void {
                $table->unsignedSmallInteger('cookie_lifetime_days')->nullable()->after('show_cookie_banner');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cms_settings', function (Blueprint $table): void {
            $table->dropColumn('cookie_lifetime_days');
        });
    }
};
