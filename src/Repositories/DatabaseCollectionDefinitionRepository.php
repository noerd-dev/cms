<?php

namespace Noerd\Cms\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Support\CollectionDefinitionData;
use Noerd\Helpers\TenantHelper;
use RuntimeException;

class DatabaseCollectionDefinitionRepository implements CollectionDefinitionRepositoryContract
{
    /**
     * Per-request resolveFields cache keyed by "tenantId:filename".
     *
     * @var array<string, array<string, mixed>|null>
     */
    private static array $requestCache = [];

    public static function resetCache(): void
    {
        self::$requestCache = [];
    }

    public function all(?int $tenantId = null): Collection
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();

        return CollectionDefinition::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('title_list')
            ->get()
            ->map(fn (CollectionDefinition $m) => $this->toData($m));
    }

    public function find(string $filename, ?int $tenantId = null): ?CollectionDefinitionData
    {
        $model = $this->findModel($filename, $tenantId);

        return $model ? $this->toData($model) : null;
    }

    public function findByKey(string $key, ?int $tenantId = null): ?CollectionDefinitionData
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();

        $model = CollectionDefinition::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('key', mb_strtoupper($key))
            ->first();

        return $model ? $this->toData($model) : null;
    }

    public function exists(string $filename, ?int $tenantId = null): bool
    {
        return $this->findModel($filename, $tenantId) !== null;
    }

    public function resolveFields(string $filename): ?array
    {
        $tenantId = TenantHelper::getSelectedTenantId();
        $cacheKey = ($tenantId ?? 'null') . ':' . $filename;

        if (array_key_exists($cacheKey, self::$requestCache)) {
            return self::$requestCache[$cacheKey];
        }

        return self::$requestCache[$cacheKey] = $this->resolveFieldsUncached($filename, $tenantId);
    }

    private function resolveFieldsUncached(string $filename, ?int $tenantId): ?array
    {
        $query = CollectionDefinition::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        $model = (clone $query)->where('filename', $filename)->first();

        if (! $model) {
            $model = $query
                ->where('key', mb_strtoupper(str_replace('-', '_', $filename)))
                ->first();
        }

        if (! $model) {
            return null;
        }

        $fields = [];
        foreach ($model->fields ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === 'collection.page_id' || $name === 'detailData.collection.page_id') {
                continue;
            }
            $fields[] = array_merge($field, [
                'name' => 'detailData.' . ltrim(preg_replace('/^(model\.|detailData\.)/', '', $name), '.'),
                'label' => $field['label'] ?? '',
                'type' => $field['type'] ?? 'text',
                'colspan' => (int) ($field['colspan'] ?? 6),
            ]);
        }

        return [
            'title' => $model->title,
            'titleList' => $model->title_list,
            'key' => $model->key,
            'description' => $model->description ?? '',
            'hasPage' => (bool) $model->has_page,
            'fields' => $fields,
        ];
    }

    public function save(CollectionDefinitionData $data, ?string $originalFilename = null, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();
        if ($tenantId === null) {
            throw new RuntimeException('Cannot save a collection definition without a tenant context.');
        }

        $existing = $originalFilename !== null
            ? $this->findModel($originalFilename, $tenantId)
            : null;

        $attributes = [
            'tenant_id' => $tenantId,
            'filename' => $data->filename,
            'key' => mb_strtoupper($data->key),
            'title' => $data->title,
            'title_list' => $data->titleList,
            'description' => $data->description,
            'has_page' => $data->hasPage,
            'fields' => $data->fields,
        ];

        if ($existing) {
            $existing->update($attributes);
            $model = $existing;
        } else {
            $attributes['created_by'] = Auth::id();
            $model = CollectionDefinition::create($attributes);
        }

        self::resetCache();

        return $model->filename;
    }

    public function copy(string $filename, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();
        $source = $this->findModel($filename, $tenantId);
        if (! $source) {
            throw new RuntimeException("Definition '{$filename}' not found.");
        }

        $newFilename = $filename . '2';
        if ($this->findModel($newFilename, $tenantId)) {
            throw new RuntimeException("Definition '{$newFilename}' already exists.");
        }

        CollectionDefinition::create([
            'tenant_id' => $tenantId,
            'filename' => $newFilename,
            'key' => $source->key . '2',
            'title' => $source->title . '2',
            'title_list' => $source->title_list . '2',
            'description' => $source->description,
            'has_page' => $source->has_page,
            'fields' => $source->fields,
            'created_by' => Auth::id(),
        ]);

        self::resetCache();

        return $newFilename;
    }

    public function delete(string $filename, ?int $tenantId = null): void
    {
        $model = $this->findModel($filename, $tenantId);
        $model?->delete();

        self::resetCache();
    }

    public function isWritable(): bool
    {
        return true;
    }

    private function findModel(string $filename, ?int $tenantId): ?CollectionDefinition
    {
        $tenantId = $tenantId ?? TenantHelper::getSelectedTenantId();

        return CollectionDefinition::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('filename', $filename)
            ->first();
    }

    private function toData(CollectionDefinition $model): CollectionDefinitionData
    {
        $fields = [];
        foreach ($model->fields ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');
            $fields[] = array_merge($field, [
                'name' => preg_replace('/^(model\.|detailData\.)/', '', $name),
                'label' => $field['label'] ?? '',
                'type' => $field['type'] ?? 'text',
                'colspan' => (int) ($field['colspan'] ?? 6),
            ]);
        }

        return new CollectionDefinitionData(
            filename: $model->filename,
            key: $model->key,
            title: $model->title,
            titleList: $model->title_list,
            description: $model->description,
            hasPage: (bool) $model->has_page,
            fields: $fields,
            createdBy: $model->created_by,
        );
    }
}
