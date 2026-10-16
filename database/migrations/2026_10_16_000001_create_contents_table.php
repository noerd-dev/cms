<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Contents become the shared base of every page-like record: a content row
 | carries the name and the elements, an extension table (cms_pages, and the
 | tables of other modules) carries what only its type needs and points at the
 | content through content_id. Existing pages keep their ids — each gets one
 | content of type cms.page, its elements move along with their ids.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('contents')) {
            Schema::create('contents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('type', 64);
                $table->json('name')->nullable();
                $table->json('custom_attributes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'type']);

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        if (! Schema::hasColumn('cms_pages', 'content_id')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->unsignedBigInteger('content_id')->nullable()->after('tenant_id');
            });
        }

        $this->backfillPageContents();

        if (Schema::hasTable('cms_page_elements')) {
            $this->moveElementsToContents();
        }

        if (Schema::hasColumn('cms_pages', 'name')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }

        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->unique('content_id');
            $table->foreign('content_id')->references('id')->on('contents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->dropKeysOn('cms_pages', 'content_id');

        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->json('name')->nullable()->after('collection_id');
        });

        DB::table('cms_pages')->orderBy('id')->each(function (object $page): void {
            $name = DB::table('contents')->where('id', $page->content_id)->value('name');
            DB::table('cms_pages')->where('id', $page->id)->update(['name' => $name]);
        });

        Schema::table('content_elements', function (Blueprint $table): void {
            $table->unsignedBigInteger('page_id')->nullable()->after('id');
        });

        DB::table('cms_pages')->orderBy('id')->each(function (object $page): void {
            DB::table('content_elements')->where('content_id', $page->content_id)->update(['page_id' => $page->id]);
        });

        DB::table('content_elements')->whereNull('page_id')->delete();

        $this->dropKeysOn('content_elements', 'content_id');

        Schema::table('content_elements', function (Blueprint $table): void {
            $table->dropColumn('content_id');
        });

        Schema::rename('content_elements', 'cms_page_elements');

        Schema::table('cms_page_elements', function (Blueprint $table): void {
            $table->index(['page_id', 'sort']);
            $table->foreign('page_id')->references('id')->on('cms_pages')->onDelete('cascade');
        });

        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropColumn('content_id');
        });

        Schema::dropIfExists('contents');
    }

    /**
     * Drop every foreign key and index on the column. Older installations
     * carry them under historical names (`element_page_page_id_foreign`), so
     * they are looked up instead of derived from the table name.
     */
    private function dropKeysOn(string $table, string $column): void
    {
        // sqlite drops a foreign key by its columns only (it has no names).
        $byColumns = Schema::getConnection()->getDriverName() === 'sqlite';

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                Schema::table($table, fn(Blueprint $blueprint) => $blueprint->dropForeign($byColumns ? $foreignKey['columns'] : $foreignKey['name']));
            }
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (! $index['primary'] && in_array($column, $index['columns'], true)) {
                Schema::table($table, fn(Blueprint $blueprint) => $blueprint->dropIndex($index['name']));
            }
        }
    }

    private function backfillPageContents(): void
    {
        $hasName = Schema::hasColumn('cms_pages', 'name');

        DB::table('cms_pages')
            ->whereNull('content_id')
            ->orderBy('id')
            ->each(function (object $page) use ($hasName): void {
                $contentId = DB::table('contents')->insertGetId([
                    'tenant_id' => $page->tenant_id,
                    'type' => 'cms.page',
                    'name' => $hasName ? $page->name : null,
                    'created_at' => $page->created_at,
                    'updated_at' => $page->updated_at,
                ]);

                DB::table('cms_pages')->where('id', $page->id)->update(['content_id' => $contentId]);
            });
    }

    /**
     * The element ids stay as they are — cms_collections.element_page_id keeps
     * pointing at the same rows after the rename.
     */
    private function moveElementsToContents(): void
    {
        Schema::table('cms_page_elements', function (Blueprint $table): void {
            $table->unsignedBigInteger('content_id')->nullable()->after('id');
        });

        DB::table('cms_pages')->orderBy('id')->each(function (object $page): void {
            DB::table('cms_page_elements')->where('page_id', $page->id)->update(['content_id' => $page->content_id]);
        });

        DB::table('cms_page_elements')->whereNull('content_id')->delete();

        $this->dropKeysOn('cms_page_elements', 'page_id');

        Schema::table('cms_page_elements', function (Blueprint $table): void {
            $table->dropColumn('page_id');
        });

        Schema::rename('cms_page_elements', 'content_elements');

        Schema::table('content_elements', function (Blueprint $table): void {
            $table->unsignedBigInteger('content_id')->nullable(false)->change();
            $table->index(['content_id', 'sort']);
            $table->foreign('content_id')->references('id')->on('contents')->onDelete('cascade');
        });
    }
};
