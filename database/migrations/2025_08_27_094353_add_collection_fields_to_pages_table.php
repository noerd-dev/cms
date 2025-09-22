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
        Schema::table('pages', function (Blueprint $table): void {
            $table->foreignId('collection_id')->nullable()->after('tenant_id')->constrained('collections')->onDelete('cascade');
            $table->json('data')->nullable()->after('slug');
            $table->integer('sort')->nullable()->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropForeign(['collection_id']);
            $table->dropColumn(['collection_id', 'data', 'sort']);
        });
    }
};
