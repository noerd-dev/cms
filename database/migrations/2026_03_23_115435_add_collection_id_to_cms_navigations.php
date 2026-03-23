<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cms_navigations', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->after('page_id')->constrained('collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cms_navigations', function (Blueprint $table) {
            $table->dropForeign(['collection_id']);
            $table->dropColumn('collection_id');
        });
    }
};
