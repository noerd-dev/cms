<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade path from noerd/cms 0.1.x, whose content tables carried no module
 * prefix. A fresh installation creates the prefixed tables directly (the
 * create migrations above already use the new names), so every rename below
 * is a no-op there. The schema additions of 0.2 that touch renamed tables
 * (unique key, text column, indexes, foreign key) are applied here as well,
 * because the create migrations do not run again on an existing database.
 *
 * Deliberately ordered BEFORE create_cms_redirects_table: an installation that
 * has not run that migration yet would otherwise create the redirects table
 * against the not-yet-renamed pages table.
 */
return new class extends Migration {
    /** @var array<string, string> old table name => new table name */
    private const RENAMES = [
        'pages' => 'cms_pages',
        'articles' => 'cms_articles',
        'authors' => 'cms_authors',
        'collections' => 'cms_collections',
        'collection_definitions' => 'cms_collection_definitions',
        'element_page' => 'cms_page_elements',
        'form_types' => 'cms_form_types',
        'form_requests' => 'cms_form_requests',
        'global_parameters' => 'cms_global_parameters',
    ];

    public function up(): void
    {
        $renamed = false;

        foreach (self::RENAMES as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
                $renamed = true;
            }
        }

        if (! $renamed) {
            return;
        }

        Schema::table('cms_global_parameters', function (Blueprint $table): void {
            $table->text('value')->nullable()->change();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->index(['tenant_id', 'collection_id']);
        });

        Schema::table('cms_page_elements', function (Blueprint $table): void {
            $table->index(['page_id', 'sort']);
        });

        Schema::table('cms_form_requests', function (Blueprint $table): void {
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('cms_articles', function (Blueprint $table): void {
            $table->index(['tenant_id', 'is_active', 'publication_date']);
        });

        Schema::table('cms_collections', function (Blueprint $table): void {
            $table->foreign('element_page_id')->references('id')->on('cms_page_elements')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        foreach (array_reverse(self::RENAMES) as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }
};
