<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cms_authors', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('custom_attributes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('cms_articles', function (Blueprint $table): void {
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

            $table->index('tenant_id');
            // Article::published() filters active articles by publication date.
            $table->index(['tenant_id', 'is_active', 'publication_date']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('cms_authors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_articles');
        Schema::dropIfExists('cms_authors');
    }
};
