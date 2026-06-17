<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('authors')) {
            Schema::create('authors', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->text('bio')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('custom_attributes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('articles')) {
            Schema::create('articles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('author_id')->nullable();
                $table->json('title');
                $table->json('slug');
                $table->text('body')->nullable();
                $table->string('featured_image')->nullable();
                $table->json('custom_attributes')->nullable();
                $table->date('publication_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('author_id')->references('id')->on('authors')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('authors');
    }
};
