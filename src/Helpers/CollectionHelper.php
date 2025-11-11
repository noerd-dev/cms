<?php

namespace Noerd\Cms\Helpers;

use Exception;
use Symfony\Component\Yaml\Yaml;

class CollectionHelper
{
    public static function getCollectionFields(?string $collection): ?array
    {
        if ($collection === null) {
            return null;
        }

        try {
            $path = base_path('content/collections/'.$collection.'.yml');
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

    public static function getCollectionTable(string $collection): array
    {
        $table = [];
        $collectionFields = self::getCollectionFields($collection);

        foreach ($collectionFields['fields'] as $collectionField) {
            $tableColumn = [];

            $tableColumn['width'] = $collectionField['width'] ?? 10;
            $tableColumn['label'] = $collectionField['label'] ?? $collectionField['name'];
            $tableColumn['field'] = str_replace('model.', '', $collectionField['name']);
            if ($tableColumn['field'] !== 'page_id') {
                $table[] = $tableColumn;
            }
        }

        return $table;
    }
}
