<?php

declare(strict_types=1);

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
}
