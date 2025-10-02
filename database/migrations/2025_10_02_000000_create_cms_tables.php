<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Elements table
        if (! Schema::hasTable('elements')) {
            Schema::create('elements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('element_key');
                $table->timestamps();

                $table->index('tenant_id');

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // Pages table
        if (! Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('collection_id')->nullable()->constrained('collections')->onDelete('cascade');
                $table->string('name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('slug')->nullable();
                $table->string('layout')->nullable();
                $table->json('data')->nullable();
                $table->integer('sort')->nullable();
                $table->timestamps();

                $table->index('tenant_id');

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // Global parameters table
        if (! Schema::hasTable('global_parameters')) {
            Schema::create('global_parameters', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('key');
                $table->string('value');
                $table->timestamps();

                $table->index('tenant_id');

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // Collections table
        if (! Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('page_id')->nullable();
                $table->string('collection_key');
                $table->integer('sort')->default(0);
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('page_id');
                $table->index('collection_key');
                $table->index('sort');

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
            });
        }

        // Element page pivot table
        if (! Schema::hasTable('element_page')) {
            Schema::create('element_page', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('page_id');
                $table->string('element_key');
                $table->unsignedInteger('sort')->default(0);
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index('page_id');
                $table->index('element_key');

                $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
            });
        }

        // Form requests table
        if (! Schema::hasTable('form_requests')) {
            Schema::create('form_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('form');
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // CMS languages table
        if (! Schema::hasTable('cms_languages')) {
            Schema::create('cms_languages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('code', 10);
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('code');
                $table->index('is_active');
                $table->index('is_default');
                $table->index('sort_order');
                $table->unique(['tenant_id', 'code']);

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // CMS navigations table
        if (! Schema::hasTable('cms_navigations')) {
            Schema::create('cms_navigations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('navigation_key');
                $table->json('name')->nullable();
                $table->unsignedBigInteger('page_id')->nullable();
                $table->string('link')->nullable();
                $table->boolean('new_tab')->default(false);
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('navigation_key');
                $table->index('page_id');

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('page_id')->references('id')->on('pages')->onDelete('set null');
            });
        }

        // CMS settings table
        if (! Schema::hasTable('cms_settings')) {
            Schema::create('cms_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('homepage_page_id')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('homepage_page_id')->references('id')->on('pages')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_settings');
        Schema::dropIfExists('cms_navigations');
        Schema::dropIfExists('cms_languages');
        Schema::dropIfExists('form_requests');
        Schema::dropIfExists('element_page');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('global_parameters');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('elements');
    }
};
