<?php

namespace Noerd\Cms\Helpers;

use Noerd\Noerd\Helpers\StaticConfigHelper;
use Symfony\Component\Yaml\Yaml;

class FieldHelper
{
    public static function getElementFields(string $element): ?array
    {
        // Convert element key to kebab-case for yml file lookup (same as blade component naming)
        $elementFileName = str_replace('_', '-', $element);

        // Check in livewire elements directory (co-located with components)
        if (file_exists(base_path('app-modules/website/resources/views/livewire/elements/' . $elementFileName . '.yml'))) {
            $content = file_get_contents(base_path('app-modules/website/resources/views/livewire/elements/' . $elementFileName . '.yml'));

            return Yaml::parse($content ?: '');
        }

        return null;
    }

    public static function parseElementToData(string $element, ?array $data): ?array
    {
        $model = [];
        $elementFields = self::getElementFields($element);

        if (! $elementFields) {
            return null;
        }

        $flattenedFields = self::flattenFields($elementFields['fields'] ?? []);

        foreach ($flattenedFields as $elementField) {
            if (in_array($elementField['type'], ['translatableText', 'translatableRichText'])) {
                $baseKey = str_replace('model.', '', $elementField['name']);

                foreach (['de', 'en'] as $lang) {
                    $model[$baseKey][$lang] = $data[$baseKey][$lang] ?? $data[$baseKey] ?? '';
                }
            } else {
                $baseKey = str_replace('model.', '', $elementField['name']);
                $model[$baseKey] = $data[$baseKey] ?? $elementField['default'] ?? '';
            }
        }

        return $model;
    }

    public static function parseComponentToData(string $component, array $data): array
    {
        $datas = [];
        foreach ($data as $key => $value) {
            if (self::isJsonAndDecode($value)) {
                $value = self::isJsonAndDecode($value);
            }
            $datas[$key] = $value;
        }

        $model = [];
        $componentFields = StaticConfigHelper::getComponentFields($component);

        $flattenedFields = self::flattenFields($componentFields['fields'] ?? []);

        foreach ($flattenedFields as $elementField) {
            if (in_array($elementField['type'], ['translatableText', 'translatableRichText'])) {
                $baseKey = str_replace('model.', '', $elementField['name']);

                foreach (['de', 'en'] as $lang) {
                    $value = $datas[$baseKey][$lang] ?? $datas[$baseKey] ?? '';
                    if (self::isJsonAndDecode($value)) {
                        $value = self::isJsonAndDecode($value);
                    }

                    $model[$baseKey][$lang] = $value;
                }
            } else {
                $baseKey = str_replace('model.', '', $elementField['name']);
                $model[$baseKey] = $datas[$baseKey] ?? $elementField['default'] ?? '';
            }
        }

        return $model;
    }

    public static function getAllElements(): array
    {
        $elements = [];

        // Get all livewire element components from app-modules
        $livewireElementFiles = glob(base_path('app-modules/*/resources/views/livewire/elements/*.blade.php'));

        foreach ($livewireElementFiles as $livewireFile) {
            $fileName = basename($livewireFile, '.blade.php');
            // Convert kebab-case filename to snake_case for element key
            $elementKey = str_replace('-', '_', $fileName);

            // Try to find corresponding yml definition (co-located with livewire component, same naming as blade file)
            $ymlFile = base_path('app-modules/website/resources/views/livewire/elements/' . $fileName . '.yml');

            if (file_exists($ymlFile)) {
                $content = file_get_contents($ymlFile);
                $yaml = Yaml::parse($content ?: '');

                $elements[] = (object) [
                    'element_key' => $elementKey,
                    'name' => $yaml['title'] ?: ucwords(str_replace('_', ' ', $elementKey)),
                    'description' => $yaml['description'] ?? '',
                    'group' => $yaml['group'] ?? 'General',
                ];
            } else {
                // If no yml file exists, create a basic element entry
                $elements[] = (object) [
                    'element_key' => $elementKey,
                    'name' => ucwords(str_replace(['_', '-'], ' ', $elementKey)),
                    'description' => 'Auto-detected from Livewire component',
                    'group' => 'General',
                ];
            }
        }

        return $elements;
    }

    public static function getAllElementsGrouped(): array
    {
        $elements = self::getAllElements();
        $groupedElements = [];

        foreach ($elements as $element) {
            $group = $element->group ?? 'General';
            if (! isset($groupedElements[$group])) {
                $groupedElements[$group] = [];
            }
            $groupedElements[$group][] = $element;
        }

        // Sort groups alphabetically, but keep General at the top if it exists
        uksort($groupedElements, function ($a, $b) {
            if ($a === 'General') {
                return -1;
            }
            if ($b === 'General') {
                return 1;
            }

            return strcmp($a, $b);
        });

        return $groupedElements;
    }

    /**
     * Flatten nested block fields into a single array of fields.
     * Recursively extracts fields from blocks.
     */
    private static function flattenFields(array $fields): array
    {
        $flattened = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'block') {
                // Recursively flatten nested fields within the block
                $nestedFields = self::flattenFields($field['fields'] ?? []);
                $flattened = array_merge($flattened, $nestedFields);
            } elseif (isset($field['name'])) {
                // Only add fields that have a name
                $flattened[] = $field;
            }
        }

        return $flattened;
    }

    private static function isJsonAndDecode($value): mixed
    {
        // First check if it's a string (JSON must be a string)
        if (! is_string($value)) {
            return false;
        }

        // Attempt to decode
        $decoded = json_decode($value, true);

        // Check if decoding was successful
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return false;
    }
}
