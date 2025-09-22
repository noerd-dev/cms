<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('pages', 'layout')) {
                $table->string('layout')->nullable()->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            if (Schema::hasColumn('pages', 'layout')) {
                $table->dropColumn('layout');
            }
        });
    }
};
