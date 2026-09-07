<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('collection_id')->nullable()->constrained('cms_collections')->onDelete('cascade');
            $table->json('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('slug')->nullable();
            $table->string('layout')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->boolean('meta_noindex')->default(false);
            $table->string('og_image')->nullable();
            $table->json('data')->nullable();
            $table->json('custom_attributes')->nullable();
            $table->integer('sort')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            // Collection entry lists filter by tenant + collection.
            $table->index(['tenant_id', 'collection_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('cms_global_parameters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('key');
            // Translatable values are JSON language maps — far beyond 255 chars.
            $table->text('value')->nullable();
            $table->boolean('is_translatable')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            // A parameter key is looked up per tenant and must be unambiguous.
            $table->unique(['tenant_id', 'key']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('cms_page_elements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->string('element_key');
            $table->unsignedInteger('sort')->default(0);
            $table->json('data')->nullable();
            $table->timestamps();

            // Page::elements() reads the elements of one page ordered by sort.
            $table->index(['page_id', 'sort']);
            $table->index('element_key');

            $table->foreign('page_id')->references('id')->on('cms_pages')->onDelete('cascade');
        });

        Schema::create('cms_form_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('form');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            // The submissions list shows the newest entries of a tenant first.
            $table->index(['tenant_id', 'created_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('cms_navigations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('navigation_key');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('name')->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('collection_id')->nullable();
            $table->string('link')->nullable();
            $table->boolean('new_tab')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('navigation_key');
            $table->index('page_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('cms_navigations')->onDelete('cascade');
            $table->foreign('page_id')->references('id')->on('cms_pages')->onDelete('set null');
            $table->foreign('collection_id')->references('id')->on('cms_collections')->nullOnDelete();
        });

        Schema::create('cms_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('homepage_page_id')->nullable();
            $table->string('google_analytics_id')->nullable();
            $table->string('form_recipients')->nullable();
            $table->boolean('show_cookie_banner')->default(false);
            $table->unsignedSmallInteger('cookie_lifetime_days')->nullable();
            $table->timestamps();

            // One settings row per tenant — the singleton contract of the
            // settings page and of CmsSetting::formRecipientsForTenant().
            $table->unique('tenant_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('homepage_page_id')->references('id')->on('cms_pages')->onDelete('set null');
        });

        // Complete the circular pages <-> collections relationship now that the
        // pages table exists.
        Schema::table('cms_collections', function (Blueprint $table): void {
            $table->foreign('page_id')->references('id')->on('cms_pages')->onDelete('cascade');
            // An element collection owned by a page element disappears with it.
            $table->foreign('element_page_id')->references('id')->on('cms_page_elements')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_collections')) {
            try {
                Schema::table('cms_collections', function (Blueprint $table): void {
                    $table->dropForeign(['page_id']);
                    $table->dropForeign(['element_page_id']);
                });
            } catch (Throwable) {
                // sqlite cannot drop foreign keys — the tables are dropped anyway.
            }
        }

        Schema::dropIfExists('cms_settings');
        Schema::dropIfExists('cms_navigations');
        Schema::dropIfExists('cms_form_requests');
        Schema::dropIfExists('cms_page_elements');
        Schema::dropIfExists('cms_global_parameters');
        Schema::dropIfExists('cms_pages');
    }
};
