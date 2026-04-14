<?php

namespace Noerd\Cms\Contracts;

use Illuminate\Support\Collection;
use Noerd\Cms\Support\CollectionDefinitionData;
use RuntimeException;

interface CollectionDefinitionRepositoryContract
{
    /**
     * Return every collection definition available in the current scope.
     *
     * @return Collection<int, CollectionDefinitionData>
     */
    public function all(?int $tenantId = null): Collection;

    /**
     * Find a definition by its filename (e.g. "contacts").
     */
    public function find(string $filename, ?int $tenantId = null): ?CollectionDefinitionData;

    /**
     * Find a definition by its key (e.g. "CONTACTS").
     */
    public function findByKey(string $key, ?int $tenantId = null): ?CollectionDefinitionData;

    public function exists(string $filename, ?int $tenantId = null): bool;

    /**
     * Persist a definition (create or update).
     * Returns the canonical filename of the saved definition.
     *
     * @throws RuntimeException when the implementation is read-only.
     */
    public function save(CollectionDefinitionData $data, ?string $originalFilename = null, ?int $tenantId = null): string;

    /**
     * Duplicate an existing definition, suffixing its filename and key with "2".
     *
     * @throws RuntimeException when the implementation is read-only.
     */
    public function copy(string $filename, ?int $tenantId = null): string;

    /**
     * @throws RuntimeException when the implementation is read-only.
     */
    public function delete(string $filename, ?int $tenantId = null): void;

    /**
     * Resolve field definitions in the YAML-shaped array used by CollectionHelper.
     *
     * Returns null if the definition does not exist.
     *
     * @return array{title?: string, titleList?: string, key?: string, description?: string, hasPage?: bool, fields?: array<int, array<string, mixed>>}|null
     */
    public function resolveFields(string $filename): ?array;

    /**
     * Whether this implementation supports mutations (save/copy/delete).
     */
    public function isWritable(): bool;
}
