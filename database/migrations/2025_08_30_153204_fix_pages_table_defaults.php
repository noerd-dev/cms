<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            // Fix is_active column to have proper default value
            $table->boolean('is_active')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            // Revert is_active to nullable without default
            $table->boolean('is_active')->nullable()->change();
        });
    }
};
