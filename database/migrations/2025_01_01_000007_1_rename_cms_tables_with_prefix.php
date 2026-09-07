<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade path from noerd/cms 0.1.x, whose content tables carried no module
 * prefix. A fresh installation creates the prefixed tables directly (the
 * create migrations above already use the new names), so every step below
 * is a no-op there. The schema additions of 0.2 that touch renamed tables
 * (columns, unique key, text column, indexes, foreign key) are applied here
 * as well, because the create migrations do not run again on an existing
 * database — each one guarded on its own, so the migration can be re-run
 * after an aborted attempt and copes with a database older than 0.1.x.
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
        foreach (self::RENAMES as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        $this->upgradeGlobalParameters();
        $this->upgradeCollections();
        $this->ensureIndex('cms_pages', ['tenant_id', 'collection_id']);
        $this->ensureIndex('cms_page_elements', ['page_id', 'sort']);
        $this->ensureIndex('cms_form_requests', ['tenant_id', 'created_at']);
        $this->ensureIndex('cms_articles', ['tenant_id', 'is_active', 'publication_date']);
    }

    public function down(): void
    {
        foreach (array_reverse(self::RENAMES) as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }

    private function upgradeGlobalParameters(): void
    {
        if (! Schema::hasTable('cms_global_parameters')) {
            return;
        }

        Schema::table('cms_global_parameters', function (Blueprint $table): void {
            $table->text('value')->nullable()->change();

            if (! Schema::hasColumn('cms_global_parameters', 'is_translatable')) {
                $table->boolean('is_translatable')->default(false)->after('value');
            }
        });

        if (! $this->hasIndexOn('cms_global_parameters', ['tenant_id', 'key'])) {
            Schema::table('cms_global_parameters', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'key']);
            });
        }
    }

    private function upgradeCollections(): void
    {
        if (! Schema::hasTable('cms_collections')) {
            return;
        }

        // Element collections arrived after the first collections schema — an
        // older database may lack their columns entirely.
        Schema::table('cms_collections', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_collections', 'element_page_id')) {
                $table->unsignedBigInteger('element_page_id')->nullable()->after('page_id');
                $table->index('element_page_id');
            }
            if (! Schema::hasColumn('cms_collections', 'owner_field')) {
                $table->string('owner_field')->nullable()->after('collection_key');
            }
            if (! Schema::hasColumn('cms_collections', 'element_fields')) {
                $table->json('element_fields')->nullable()->after('owner_field');
            }
            if (! Schema::hasColumn('cms_collections', 'is_element_collection')) {
                $table->boolean('is_element_collection')->default(false)->after('element_fields');
                $table->index('is_element_collection');
            }
        });

        if (Schema::hasTable('cms_page_elements') && ! $this->hasForeignKeyOn('cms_collections', 'element_page_id')) {
            Schema::table('cms_collections', function (Blueprint $table): void {
                $table->foreign('element_page_id')->references('id')->on('cms_page_elements')->onDelete('cascade');
            });
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureIndex(string $table, array $columns): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $columns) || $this->hasIndexOn($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
            $blueprint->index($columns);
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndexOn(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKeyOn(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column]) {
                return true;
            }
        }

        return false;
    }
};
