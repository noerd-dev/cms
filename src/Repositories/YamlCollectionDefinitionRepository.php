<?php

namespace Noerd\Cms\Repositories;

use Illuminate\Support\Collection;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Support\CollectionDefinitionData;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class YamlCollectionDefinitionRepository implements CollectionDefinitionRepositoryContract
{
    public function __construct(private readonly string $basePath) {}

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function all(?int $tenantId = null): Collection
    {
        if (! is_dir($this->basePath)) {
            return collect();
        }

        $files = glob($this->basePath . '/*.yml') ?: [];

        return collect($files)
            ->map(fn(string $path) => $this->loadFile($path))
            ->filter()
            ->sortBy(fn(CollectionDefinitionData $d) => mb_strtolower($d->titleList))
            ->values();
    }

    public function find(string $filename, ?int $tenantId = null): ?CollectionDefinitionData
    {
        $path = $this->pathFor($filename);

        return file_exists($path) ? $this->loadFile($path) : null;
    }

    public function findByKey(string $key, ?int $tenantId = null): ?CollectionDefinitionData
    {
        $key = mb_strtoupper($key);

        return $this->all()->first(fn(CollectionDefinitionData $d) => $d->key === $key);
    }

    public function exists(string $filename, ?int $tenantId = null): bool
    {
        return file_exists($this->pathFor($filename));
    }

    public function resolveFields(string $filename): ?array
    {
        $path = $this->pathFor($filename);
        if (! file_exists($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        try {
            $fields = Yaml::parse($content) ?: [];
        } catch (Throwable) {
            return null;
        }

        if (! is_array($fields)) {
            return null;
        }

        $fields['fields'] = array_values(array_filter(
            $fields['fields'] ?? [],
            fn($field) => ($field['name'] ?? null) !== 'collection.page_id',
        ));

        return $fields;
    }

    public function save(CollectionDefinitionData $data, ?string $originalFilename = null, ?int $tenantId = null): string
    {
        throw new RuntimeException('Collection definitions are read-only in YAML mode. Deploy changes via YAML files.');
    }

    public function copy(string $filename, ?int $tenantId = null): string
    {
        throw new RuntimeException('Collection definitions are read-only in YAML mode. Deploy changes via YAML files.');
    }

    public function delete(string $filename, ?int $tenantId = null): void
    {
        throw new RuntimeException('Collection definitions are read-only in YAML mode. Deploy changes via YAML files.');
    }

    public function isWritable(): bool
    {
        return false;
    }

    private function pathFor(string $filename): string
    {
        return $this->basePath . '/' . $filename . '.yml';
    }

    private function loadFile(string $path): ?CollectionDefinitionData
    {
        try {
            $content = Yaml::parseFile($path);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($content)) {
            return null;
        }

        return CollectionDefinitionData::fromArray($content, pathinfo($path, PATHINFO_FILENAME));
    }
}
