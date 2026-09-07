<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

$migration = dirname(__DIR__, 2) . '/database/migrations/2025_01_01_000007_1_rename_cms_tables_with_prefix.php';

/**
 * @param  array<int, string>  $columns
 */
function zzCmsHasIndex(string $table, array $columns): bool
{
    return collect(Schema::getIndexes($table))->contains(fn(array $index): bool => $index['columns'] === $columns);
}

it('is a no-op on a fresh installation', function () use ($migration): void {
    $indexesBefore = collect(Schema::getIndexes('cms_collections'))->pluck('name')->sort()->values()->all();

    (require $migration)->up();

    expect(Schema::hasTable('pages'))->toBeFalse()
        ->and(Schema::hasTable('cms_pages'))->toBeTrue()
        ->and(collect(Schema::getIndexes('cms_collections'))->pluck('name')->sort()->values()->all())->toBe($indexesBefore);
});

it('renames the 0.1.x tables and completes the schema, also when re-run after an aborted attempt', function () use ($migration): void {
    // Simulate a pre-0.2 database: unprefixed tables, and collections /
    // global_parameters in their first schema without the later columns.
    Schema::disableForeignKeyConstraints();
    foreach (['cms_pages' => 'pages', 'cms_page_elements' => 'element_page'] as $new => $old) {
        Schema::rename($new, $old);
    }
    Schema::drop('cms_collections');
    Schema::create('collections', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('page_id')->nullable();
        $table->string('collection_key');
        $table->string('name')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
        $table->unique(['tenant_id', 'collection_key']);
    });
    Schema::drop('cms_global_parameters');
    Schema::create('global_parameters', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('key');
        $table->string('value');
        $table->timestamps();
    });
    Schema::enableForeignKeyConstraints();

    $up = (require $migration);
    $up->up();
    // A second run (e.g. after a failure halfway through) must not throw.
    $up->up();

    expect(Schema::hasTable('pages'))->toBeFalse()
        ->and(Schema::hasTable('cms_pages'))->toBeTrue()
        ->and(Schema::hasColumns('cms_collections', ['element_page_id', 'owner_field', 'element_fields', 'is_element_collection']))->toBeTrue()
        ->and(Schema::hasColumn('cms_global_parameters', 'is_translatable'))->toBeTrue()
        ->and(zzCmsHasIndex('cms_global_parameters', ['tenant_id', 'key']))->toBeTrue()
        ->and(zzCmsHasIndex('cms_pages', ['tenant_id', 'collection_id']))->toBeTrue()
        ->and(zzCmsHasIndex('cms_articles', ['tenant_id', 'is_active', 'publication_date']))->toBeTrue();
});
