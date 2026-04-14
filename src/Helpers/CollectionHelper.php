<?php

namespace Noerd\Cms\Helpers;

use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;

class CollectionHelper
{
    public function __construct(
        private readonly CollectionDefinitionRepositoryContract $repository,
    ) {}

    /**
     * Get collection fields from the configured storage backend.
     * Static method delegates to the container-resolved instance for mockability.
     */
    public static function getCollectionFields(?string $collection): ?array
    {
        return app(self::class)->resolveCollectionFields($collection);
    }

    /**
     * Get collection table configuration.
     * Static method delegates to the container-resolved instance for mockability.
     */
    public static function getCollectionTable(string $collection): array
    {
        return app(self::class)->resolveCollectionTable($collection);
    }

    /**
     * Get normalized field names for a collection (without 'detailData.' prefix).
     */
    public static function getCollectionFieldNames(string $collectionKey): array
    {
        $fields = self::getCollectionFields(mb_strtolower($collectionKey));
        if (! $fields || empty($fields['fields'])) {
            return [];
        }

        return array_map(fn($field) => str_replace('detailData.', '', $field['name'] ?? ''), $fields['fields']);
    }

    /**
     * Instance method: resolve collection fields via the repository.
     */
    public function resolveCollectionFields(?string $collection): ?array
    {
        if ($collection === null) {
            return null;
        }

        return $this->repository->resolveFields($collection);
    }

    /**
     * Instance method: resolve collection table configuration.
     */
    public function resolveCollectionTable(string $collection): array
    {
        $table = [];
        $collectionFields = $this->resolveCollectionFields($collection);

        foreach ($collectionFields['fields'] ?? [] as $collectionField) {
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
