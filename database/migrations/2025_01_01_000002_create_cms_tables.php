<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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

        if (! Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('collection_id')->nullable()->constrained('collections')->onDelete('cascade');
                $table->string('name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('slug')->nullable();
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

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('global_parameters')) {
            Schema::create('global_parameters', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('key');
                $table->string('value');
                $table->boolean('is_translatable')->default(false);
                $table->timestamps();

                $table->index('tenant_id');

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

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

        if (! Schema::hasTable('cms_navigations')) {
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
                $table->foreign('page_id')->references('id')->on('pages')->onDelete('set null');
                $table->foreign('collection_id')->references('id')->on('collections')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('cms_settings')) {
            Schema::create('cms_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('homepage_page_id')->nullable();
                $table->string('google_analytics_id')->nullable();
                $table->boolean('show_cookie_banner')->default(false);
                $table->timestamps();

                $table->index('tenant_id');

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('homepage_page_id')->references('id')->on('pages')->onDelete('set null');
            });
        }

        // Complete the circular pages <-> collections relationship now that pages exists.
        if (Schema::hasTable('collections') && Schema::hasTable('pages') && ! $this->collectionsHasPageIdForeign()) {
            try {
                Schema::table('collections', function (Blueprint $table): void {
                    $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // Foreign key already exists, skip.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('collections')) {
            try {
                Schema::table('collections', function (Blueprint $table): void {
                    $table->dropForeign(['page_id']);
                });
            } catch (\Throwable $e) {
                // Foreign key already dropped, skip.
            }
        }

        Schema::dropIfExists('cms_settings');
        Schema::dropIfExists('cms_navigations');
        Schema::dropIfExists('form_requests');
        Schema::dropIfExists('element_page');
        Schema::dropIfExists('global_parameters');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('elements');
    }

    private function collectionsHasPageIdForeign(): bool
    {
        foreach (Schema::getForeignKeys('collections') as $foreignKey) {
            if (in_array('page_id', $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
