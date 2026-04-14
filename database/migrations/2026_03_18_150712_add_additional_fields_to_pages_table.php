<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('pages', 'additional_fields')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->json('additional_fields')->nullable()->after('data');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('additional_fields');
        });
    }
};
