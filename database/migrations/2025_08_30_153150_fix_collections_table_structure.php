<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            // Add missing columns to match migration expectations
            $table->unsignedBigInteger('page_id')->nullable()->after('tenant_id');
            $table->integer('sort')->default(0)->after('collection_key');
            $table->json('data')->nullable()->after('sort');
            
            // Add missing indexes
            $table->index('page_id');
            $table->index('collection_key');
            $table->index('sort');
            
            // Add missing foreign key
            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            // Drop foreign key and indexes first
            $table->dropForeign(['page_id']);
            $table->dropIndex(['page_id']);
            $table->dropIndex(['collection_key']);  
            $table->dropIndex(['sort']);
            
            // Drop the columns
            $table->dropColumn(['page_id', 'sort', 'data']);
        });
    }
};
