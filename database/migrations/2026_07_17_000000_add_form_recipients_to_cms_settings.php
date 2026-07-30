<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('cms_settings', 'form_recipients')) {
            Schema::table('cms_settings', function (Blueprint $table): void {
                $table->string('form_recipients')->nullable()->after('google_analytics_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cms_settings', function (Blueprint $table): void {
            $table->dropColumn('form_recipients');
        });
    }
};
