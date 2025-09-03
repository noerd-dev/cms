<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Only proceed if the pages table exists
        if (Schema::hasTable('pages')) {
            // Check if is_active column exists
            if (Schema::hasColumn('pages', 'is_active')) {
                // First, update any NULL values to the default value (true)
                DB::table('pages')->whereNull('is_active')->update(['is_active' => true]);
                
                Schema::table('pages', function (Blueprint $table): void {
                    // Fix is_active column to have proper default value
                    $table->boolean('is_active')->default(true)->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Only proceed if the pages table exists
        if (Schema::hasTable('pages') && Schema::hasColumn('pages', 'is_active')) {
            Schema::table('pages', function (Blueprint $table): void {
                // Revert is_active to nullable without default
                $table->boolean('is_active')->nullable()->change();
            });
        }
    }
};
