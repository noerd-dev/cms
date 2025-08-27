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
        Schema::dropIfExists('collection_rows');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('collection_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('slug');
            $table->foreignId('collection_id')->constrained()->onDelete('cascade');
            $table->integer('sort')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }
};
