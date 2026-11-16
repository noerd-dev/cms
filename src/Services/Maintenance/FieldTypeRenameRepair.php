<?php

declare(strict_types=1);

namespace Noerd\Cms\Services\Maintenance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CMS field types were renamed to camelCase in 0.6: `element-collection`
 * is `elementCollection`, `homepage-select` is `homepageSelect`. The YAML
 * shipped with the modules is rewritten by hand; this rewrites the field
 * schemas STORED in the database — the fields of a collection definition
 * (`cms_collection_definitions.fields`) and the row schema of an element
 * collection (`cms_collections.element_fields`), nested schemas included.
 *
 * Idempotent: a schema in the current vocabulary is not written again. Data
 * only — no value of an entry changes.
 *
 * @internal
 */
final class FieldTypeRenameRepair
{
    /**
     * @var array<string, string> old type => new type
     */
    public const TYPES = [
        'element-collection' => 'elementCollection',
        'homepage-select' => 'homepageSelect',
    ];

    /**
     * @return int the number of rows rewritten
     */
    public function run(): int
    {
        return $this->rewrite('cms_collection_definitions', 'fields')
            + $this->rewrite('cms_collections', 'element_fields');
    }

    /**
     * A field list with every renamed type in its current name.
     *
     * @param  array<array-key, mixed>  $fields
     * @return array<array-key, mixed>
     */
    public function renameTypes(array $fields): array
    {
        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            if (is_string($field['type'] ?? null) && isset(self::TYPES[$field['type']])) {
                $field['type'] = self::TYPES[$field['type']];
            }

            if (is_array($field['fields'] ?? null)) {
                $field['fields'] = $this->renameTypes($field['fields']);
            }

            $fields[$index] = $field;
        }

        return $fields;
    }

    private function rewrite(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $rewritten = 0;

        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->select(['id', $column])
            ->chunkById(200, function ($rows) use ($table, $column, &$rewritten): void {
                foreach ($rows as $row) {
                    $fields = json_decode((string) $row->{$column}, true);

                    if (! is_array($fields)) {
                        continue;
                    }

                    $renamed = $this->renameTypes($fields);

                    if ($renamed === $fields) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($renamed)]);
                    $rewritten++;
                }
            });

        return $rewritten;
    }
}
