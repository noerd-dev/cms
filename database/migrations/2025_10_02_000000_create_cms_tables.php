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

        // Add page_id foreign key to collections (created in earlier migration)
        if (Schema::hasTable('collections') && Schema::hasTable('pages')) {
            try {
                Schema::table('collections', function (Blueprint $table): void {
                    $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Foreign key already exists, skip
            }
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

        // Note: languages table is now created in the noerd module
        // See: app-modules/noerd/database/migrations/2025_01_01_000003_create_languages_table.php

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
        // Note: languages table is now managed by the noerd module
        Schema::dropIfExists('form_requests');
        Schema::dropIfExists('element_page');
        Schema::dropIfExists('global_parameters');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('elements');
        // Note: collections table is dropped in its own migration (2025_10_01_000000_create_collections_table)
    }
};
