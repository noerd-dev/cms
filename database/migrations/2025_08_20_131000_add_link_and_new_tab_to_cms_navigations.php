<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cms_navigations', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_navigations', 'link')) {
                $table->string('link')->nullable()->after('page_id');
            }
            if (! Schema::hasColumn('cms_navigations', 'new_tab')) {
                $table->boolean('new_tab')->default(false)->after('link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cms_navigations', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_navigations', 'new_tab')) {
                $table->dropColumn('new_tab');
            }
            if (Schema::hasColumn('cms_navigations', 'link')) {
                $table->dropColumn('link');
            }
        });
    }
};
