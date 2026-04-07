<?php

namespace Noerd\Cms\Helpers;

use Exception;
use Symfony\Component\Yaml\Yaml;

class CollectionHelper
{
    /**
     * Get collection fields from YAML configuration.
     * Static method delegates to container-resolved instance for mockability.
     */
    public static function getCollectionFields(?string $collection): ?array
    {
        return app(self::class)->resolveCollectionFields($collection);
    }

    /**
     * Get collection table configuration.
     * Static method delegates to container-resolved instance for mockability.
     */
    public static function getCollectionTable(string $collection): array
    {
        return app(self::class)->resolveCollectionTable($collection);
    }

    /**
     * Instance method: resolve collection fields from YAML file.
     */
    public function resolveCollectionFields(?string $collection): ?array
    {
        if ($collection === null) {
            return null;
        }

        try {
            $path = base_path('app-configs/cms/collections/' . $collection . '.yml');
            $content = file_get_contents($path);
        } catch (Exception $e) {
            return null;
        }
        $fields = Yaml::parse($content ?: '');

        foreach ($fields['fields'] as $key => $item) {
            if (isset($item['name']) && $item['name'] === 'collection.page_id') {
                unset($fields['fields'][$key]);
            }
        }

        return $fields;
    }

    /**
     * Get normalized field names for a collection (without 'detailData.' prefix).
     */
    public static function getCollectionFieldNames(string $collectionKey): array
    {
        $fields = self::getCollectionFields(strtolower($collectionKey));
        if (! $fields || empty($fields['fields'])) {
            return [];
        }

        return array_map(function ($field) {
            return str_replace('detailData.', '', $field['name'] ?? '');
        }, $fields['fields']);
    }

    /**
     * Instance method: resolve collection table configuration.
     */
    public function resolveCollectionTable(string $collection): array
    {
        $table = [];
        $collectionFields = $this->resolveCollectionFields($collection);

        foreach ($collectionFields['fields'] as $collectionField) {
            $tableColumn = [];

            $tableColumn['width'] = $collectionField['width'] ?? 10;
            $tableColumn['label'] = $collectionField['label'] ?? $collectionField['name'];
            $tableColumn['field'] = str_replace('detailData.', '', $collectionField['name']);
            if ($tableColumn['field'] !== 'page_id') {
                $table[] = $tableColumn;
            }
        }

        return $table;
    }
}
