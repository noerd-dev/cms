<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * This migration creates the collections table BEFORE create_cms_tables
     * to resolve the circular dependency between pages and collections.
     */
    public function up(): void
    {
        if (! Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('page_id')->nullable();
                $table->string('collection_key');
                $table->integer('sort')->default(0);
                $table->json('data')->nullable();
                $table->string('name')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('page_id');
                $table->index('collection_key');
                $table->index('sort');
                $table->unique(['tenant_id', 'collection_key']);

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
