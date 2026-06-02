<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize the previous "internal collection" naming to "element collection":
     * rename the columns and the INTERNAL_* collection-key prefix. No-op on a fresh
     * install where the element-collection columns already exist.
     */
    public function up(): void
    {
        if (Schema::hasColumn('collections', 'is_internal') && ! Schema::hasColumn('collections', 'is_element_collection')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->renameColumn('is_internal', 'is_element_collection');
            });
        }

        if (Schema::hasColumn('collections', 'internal_fields') && ! Schema::hasColumn('collections', 'element_fields')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->renameColumn('internal_fields', 'element_fields');
            });
        }

        DB::table('collections')
            ->where('collection_key', 'like', 'INTERNAL\_%')
            ->update(['collection_key' => DB::raw("CONCAT('ELEMENT_', SUBSTRING(collection_key, 10))")]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('collections', 'is_element_collection') && ! Schema::hasColumn('collections', 'is_internal')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->renameColumn('is_element_collection', 'is_internal');
            });
        }

        if (Schema::hasColumn('collections', 'element_fields') && ! Schema::hasColumn('collections', 'internal_fields')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->renameColumn('element_fields', 'internal_fields');
            });
        }

        DB::table('collections')
            ->where('collection_key', 'like', 'ELEMENT\_%')
            ->update(['collection_key' => DB::raw("CONCAT('INTERNAL_', SUBSTRING(collection_key, 9))")]);
    }
};
