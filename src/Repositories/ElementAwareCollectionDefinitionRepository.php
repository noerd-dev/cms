<?php

namespace Noerd\Cms\Repositories;

use Illuminate\Support\Collection as SupportCollection;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Support\CollectionDefinitionData;
use Noerd\Helpers\TenantHelper;

/**
 * Decorates the collection definition repository so that element collections —
 * which have no definition row of their own — resolve their schema from the
 * owning DB collection's stored `element_fields`.
 *
 * Element collections are intentionally excluded from `all()` so they never appear
 * in navigation or the collection-definitions list.
 */
class ElementAwareCollectionDefinitionRepository implements CollectionDefinitionRepositoryContract
{
    public function __construct(
        private readonly CollectionDefinitionRepositoryContract $inner,
        private readonly ElementCollectionService $elementCollections,
    ) {}

    /**
     * The wrapped repository.
     */
    public function inner(): CollectionDefinitionRepositoryContract
    {
        return $this->inner;
    }

    public function all(?int $tenantId = null): SupportCollection
    {
        return $this->inner->all($tenantId);
    }

    public function find(string $filename, ?int $tenantId = null): ?CollectionDefinitionData
    {
        $elementCollection = $this->findElement($filename, $tenantId);

        if ($elementCollection) {
            return $this->toData($elementCollection);
        }

        return $this->inner->find($filename, $tenantId);
    }

    public function findByKey(string $key, ?int $tenantId = null): ?CollectionDefinitionData
    {
        $elementCollection = $this->findElement($key, $tenantId);

        if ($elementCollection) {
            return $this->toData($elementCollection);
        }

        return $this->inner->findByKey($key, $tenantId);
    }

    public function exists(string $filename, ?int $tenantId = null): bool
    {
        return $this->findElement($filename, $tenantId) !== null
            || $this->inner->exists($filename, $tenantId);
    }

    public function resolveFields(string $filename): ?array
    {
        $elementCollection = $this->findElement($filename, null);

        if ($elementCollection) {
            return $this->elementCollections->schemaFor($elementCollection);
        }

        return $this->inner->resolveFields($filename);
    }

    public function save(CollectionDefinitionData $data, ?string $originalFilename = null, ?int $tenantId = null): string
    {
        return $this->inner->save($data, $originalFilename, $tenantId);
    }

    public function copy(string $filename, ?int $tenantId = null): string
    {
        return $this->inner->copy($filename, $tenantId);
    }

    public function delete(string $filename, ?int $tenantId = null): void
    {
        $this->inner->delete($filename, $tenantId);
    }

    private function toData(Collection $elementCollection): CollectionDefinitionData
    {
        return CollectionDefinitionData::fromArray(
            $this->elementCollections->schemaFor($elementCollection),
            mb_strtolower((string) $elementCollection->collection_key),
        );
    }

    private function findElement(string $key, ?int $tenantId): ?Collection
    {
        $tenantId ??= auth()->user()?->selected_tenant_id ?? TenantHelper::getSelectedTenantId();

        if (! $tenantId) {
            return null;
        }

        return Collection::query()
            ->where('tenant_id', $tenantId)
            ->where('is_element_collection', true)
            ->where('collection_key', mb_strtoupper($key))
            ->first();
    }
}
