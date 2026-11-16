<?php

declare(strict_types=1);

namespace Noerd\Cms\Services\Maintenance;

use Illuminate\Support\Facades\DB;
use Noerd\Cms\Helpers\FieldHelper;

/**
 * Before noerd/cms 0.6 the document editor stored a select with a plain list
 * of options (`options: ['', 'Projects', 'Services']`) as the option's array
 * INDEX (`"1"`), while every other form stores the option itself
 * (`"Projects"`). This repair rewrites such index values in
 * `content_elements.data` to the option value.
 *
 * Driven by the element YAMLs (ElementDirectories, through FieldHelper): only
 * fields of type `select` whose options are a plain list of scalars are
 * touched, and only a stored value that is a valid numeric index AND not
 * itself one of the options (a list `['1', '2']` stays as it is). Idempotent —
 * a repaired value is an option and is never touched again. Writes through
 * the query builder, so no model hook runs and `updated_at` stays.
 */
final class SelectOptionValueRepair
{
    /**
     * @return int number of element rows changed (or that would change)
     */
    public function run(bool $dryRun = false): int
    {
        $selects = $this->plainListSelects();

        if ($selects === []) {
            return 0;
        }

        $changed = 0;

        DB::table('content_elements')
            ->whereIn('element_key', array_keys($selects))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($selects, $dryRun, &$changed): void {
                foreach ($rows as $row) {
                    $data = $this->decode($row->data);

                    if ($data === null) {
                        continue;
                    }

                    $repaired = $this->repair($data, $selects[$row->element_key] ?? []);

                    if ($repaired === $data) {
                        continue;
                    }

                    $changed++;

                    if (! $dryRun) {
                        DB::table('content_elements')
                            ->where('id', $row->id)
                            ->update(['data' => json_encode($repaired, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $fields  field key => option values
     * @return array<string, mixed>
     */
    public function repair(array $data, array $fields): array
    {
        foreach ($fields as $name => $options) {
            $value = $data[$name] ?? null;

            if (! (is_int($value) || (is_string($value) && $value !== '' && ctype_digit($value)))) {
                continue;
            }

            $index = (int) $value;

            if (! array_key_exists($index, $options) || in_array((string) $value, $options, true)) {
                continue;
            }

            $data[$name] = $options[$index];
        }

        return $data;
    }

    /**
     * Element key => [field key => option values] of every select with a
     * plain list of scalar options.
     *
     * @return array<string, array<string, list<string>>>
     */
    public function plainListSelects(): array
    {
        $selects = [];

        foreach (FieldHelper::getAllElements() as $element) {
            $definition = FieldHelper::getElementFields((string) $element->element_key);

            foreach (FieldHelper::flattenFields($definition['fields'] ?? []) as $field) {
                $options = $field['options'] ?? null;

                if (($field['type'] ?? null) !== 'select' || ! is_array($options) || $options === [] || ! array_is_list($options)) {
                    continue;
                }

                if (array_filter($options, fn($option): bool => ! is_scalar($option)) !== []) {
                    continue;
                }

                $name = (string) preg_replace('/^\w+\./', '', (string) ($field['name'] ?? ''));

                if ($name !== '') {
                    $selects[(string) $element->element_key][$name] = array_map('strval', $options);
                }
            }
        }

        return $selects;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(mixed $data): ?array
    {
        for ($depth = 0; is_string($data) && $depth < 3; $depth++) {
            $data = json_decode($data, true);
        }

        return is_array($data) ? $data : null;
    }
}
