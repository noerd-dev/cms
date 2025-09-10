<?php

namespace Noerd\Website\Traits;

trait NoerdElement
{
    public array $collections = [];

    public $element;

    public function mount($data = [], array $collections = []): void
    {
        $this->element = is_object($data) ? $data : (object) $data;
        $this->collections = $collections;
    }

    public function collection(string $key): array
    {
        foreach ($this->collections as $collection) {
            if (mb_strtolower(($collection['key'] ?? '')) === $key) {
                return $collection['rows'] ?? [];
            }
        }

        return [];
    }

    public function getAllCollections(): array
    {
        return $this->collections;
    }

    public function hasCollection(string $key): bool
    {
        return ! empty($this->collection($key));
    }

    public function getCollectionData(string $key, mixed $default = []): array
    {
        $collection = $this->collection($key);

        return ! empty($collection) ? $collection : $default;
    }
}
