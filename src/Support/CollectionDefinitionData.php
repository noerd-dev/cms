<?php

namespace Noerd\Cms\Support;

final class CollectionDefinitionData
{
    /**
     * @param  array<int, array{name: string, label: string, type: string, colspan: int}>  $fields
     */
    public function __construct(
        public string $filename,
        public string $key,
        public string $title,
        public string $titleList,
        public ?string $description,
        public bool $hasPage,
        public array $fields,
        public ?int $createdBy = null,
    ) {}

    /**
     * Build from a YAML-shaped array. Fields may use the "detailData." prefix which is stripped.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $filename = null): self
    {
        $resolvedFilename = $filename ?? (string) ($data['filename'] ?? '');
        $key = (string) ($data['key'] ?? mb_strtoupper(str_replace('-', '_', $resolvedFilename)));

        $fields = [];
        foreach ($data['fields'] ?? [] as $field) {
            $name = (string) ($field['name'] ?? '');
            $fields[] = [
                'name' => preg_replace('/^(model\.|detailData\.)/', '', $name),
                'label' => (string) ($field['label'] ?? ''),
                'type' => (string) ($field['type'] ?? 'text'),
                'colspan' => (int) ($field['colspan'] ?? 6),
            ];
        }

        return new self(
            filename: $resolvedFilename,
            key: $key,
            title: (string) ($data['title'] ?? ''),
            titleList: (string) ($data['titleList'] ?? $data['title_list'] ?? $resolvedFilename),
            description: $data['description'] ?? null,
            hasPage: (bool) ($data['hasPage'] ?? $data['has_page'] ?? false),
            fields: $fields,
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'key' => $this->key,
            'title' => $this->title,
            'titleList' => $this->titleList,
            'description' => $this->description,
            'hasPage' => $this->hasPage,
            'fields' => $this->fields,
            'created_by' => $this->createdBy,
        ];
    }

    /**
     * Produce the YAML shape (fields prefixed with "detailData.") for writing to disk.
     *
     * @return array<string, mixed>
     */
    public function toYamlArray(): array
    {
        $yamlFields = [];
        foreach ($this->fields as $field) {
            $yamlFields[] = [
                'name' => 'detailData.' . ltrim($field['name'], '.'),
                'label' => $field['label'],
                'type' => $field['type'],
                'colspan' => (int) $field['colspan'],
            ];
        }

        return [
            'title' => $this->title,
            'titleList' => $this->titleList,
            'key' => $this->key,
            'description' => $this->description ?? '',
            'hasPage' => $this->hasPage,
            'fields' => $yamlFields,
        ];
    }
}
