<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('authors', 'additional_fields')) {
            Schema::table('authors', function (Blueprint $table) {
                $table->json('additional_fields')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropColumn('additional_fields');
        });
    }
};
