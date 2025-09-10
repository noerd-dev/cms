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
        Schema::dropIfExists('page_collection');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('page_collection', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->foreignId('collection_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['page_id', 'collection_id']);
            $table->index(['tenant_id', 'sort_order']);
        });
    }
};
