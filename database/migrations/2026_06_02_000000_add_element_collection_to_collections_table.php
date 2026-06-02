<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add element-collection support: a collection owned by a single collection
     * entry (page_id) that backs one repeater-like field. Hidden from navigation
     * and from collection selection lists.
     *
     * Guards skip creation when the legacy "internal" columns are still present;
     * the follow-up rename migration normalizes those.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            if (! Schema::hasColumn('collections', 'is_element_collection') && ! Schema::hasColumn('collections', 'is_internal')) {
                $table->boolean('is_element_collection')->default(false)->index()->after('collection_key');
            }
            if (! Schema::hasColumn('collections', 'owner_field')) {
                $table->string('owner_field')->nullable()->after('collection_key');
            }
            if (! Schema::hasColumn('collections', 'element_fields') && ! Schema::hasColumn('collections', 'internal_fields')) {
                $table->json('element_fields')->nullable()->after('owner_field');
            }
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            foreach (['element_fields', 'owner_field', 'is_element_collection'] as $column) {
                if (Schema::hasColumn('collections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
