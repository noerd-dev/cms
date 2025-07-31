<?php

namespace Noerd\Cms\Helpers;

use Noerd\Noerd\Helpers\StaticConfigHelper;
use Symfony\Component\Yaml\Yaml;

class FieldHelper
{
    public static function getElementFields(string $element): array
    {
        if (file_exists(base_path('content/elements/' . $element . '.yml'))) {
            $content = file_get_contents(base_path('content/elements/' . $element . '.yml'));

            return Yaml::parse($content ?: '');
        }
        throw new \Exception("Element '{$element}' not found.");
    }

    public static function parseElementToData(string $element, ?array $data): array
    {
        $model = [];
        $elementFields = self::getElementFields($element);

        foreach ($elementFields['fields'] as $elementField) {
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

        foreach ($componentFields['fields'] as $elementField) {
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
        $elementPath = base_path('content/elements');
        
        if (!is_dir($elementPath)) {
            return $elements;
        }

        $files = glob($elementPath . '/*.yml');
        
        foreach ($files as $file) {
            $elementKey = basename($file, '.yml');
            $content = file_get_contents($file);
            $yaml = Yaml::parse($content ?: '');
            
            $elements[] = (object) [
                'element_key' => $elementKey,
                'name' => $yaml['title'] ?: ucwords(str_replace('_', ' ', $elementKey)),
                'description' => $yaml['description'] ?? ''
            ];
        }
        
        return $elements;
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
