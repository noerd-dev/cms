<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow an element collection to be owned by a page ELEMENT (element_page),
     * not only by a collection entry (page). Exactly one of page_id / element_page_id
     * is set per element collection.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            if (! Schema::hasColumn('collections', 'element_page_id')) {
                $table->unsignedBigInteger('element_page_id')->nullable()->index()->after('page_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            if (Schema::hasColumn('collections', 'element_page_id')) {
                $table->dropIndex(['element_page_id']);
                $table->dropColumn('element_page_id');
            }
        });
    }
};
