<?php

namespace Noerd\Cms\Services;

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;

/**
 * Manages "element" collections: hidden collections that back one repeater-like
 * field and are owned by exactly one of:
 *   - a collection entry (page)         → owner type "page",         column page_id
 *   - a page element instance           → owner type "element_page", column element_page_id
 *
 * Each (owner, field) pair maps to one element collection via a deterministic key.
 */
class ElementCollectionService
{
    public const OWNER_PAGE = 'page';

    public const OWNER_ELEMENT_PAGE = 'element_page';

    /**
     * Deterministic, tenant-unique key for the element collection backing a field.
     * Element-page owners use an EP_ prefix so their ids cannot collide with page ids.
     */
    public function keyFor(string $ownerType, int $ownerId, string $ownerField): string
    {
        $prefix = $ownerType === self::OWNER_ELEMENT_PAGE ? 'ELEMENT_EP_' : 'ELEMENT_';

        return $prefix.$ownerId.'_'.mb_strtoupper($this->normalizeFieldName($ownerField));
    }

    /**
     * Create (or return the existing) element collection bound to an owner + field.
     *
     * @param  array<int, array<string, mixed>>  $rowFields  The field schema for each row (the repeater's nested fields).
     */
    public function ensure(string $ownerType, int $ownerId, int $tenantId, string $ownerField, array $rowFields, string $name): Collection
    {
        $field = $this->normalizeFieldName($ownerField);
        $ownerColumn = $ownerType === self::OWNER_ELEMENT_PAGE ? 'element_page_id' : 'page_id';

        return Collection::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'collection_key' => $this->keyFor($ownerType, $ownerId, $field),
            ],
            [
                $ownerColumn => $ownerId,
                'is_element_collection' => true,
                'owner_field' => $field,
                'element_fields' => $this->normalizeRowFields($rowFields),
                'name' => $name,
                'created_by' => auth()->id(),
            ],
        );
    }

    /**
     * Move inline repeater items into the element collection for this owner+field
     * and return it. Idempotent: if the collection already has rows, they are left
     * untouched and no duplicates are created.
     *
     * @param  array<int, mixed>  $items  The inline repeater items (each an array, e.g. ['text' => ['de' => ...]]).
     * @param  array<int, array<string, mixed>>  $rowFields  The row field schema.
     */
    public function importItems(string $ownerType, int $ownerId, int $tenantId, string $ownerField, array $items, array $rowFields, string $name): Collection
    {
        $elementCollection = $this->ensure($ownerType, $ownerId, $tenantId, $ownerField, $rowFields, $name);

        if ($elementCollection->rows()->count() === 0) {
            foreach (array_values($items) as $sort => $item) {
                Page::create([
                    'tenant_id' => $tenantId,
                    'collection_id' => $elementCollection->id,
                    'name' => null,
                    'slug' => null,
                    'is_active' => true,
                    'data' => is_array($item) ? $item : ['text' => $item],
                    'sort' => $sort,
                ]);
            }
        }

        return $elementCollection;
    }

    /**
     * Build the YAML-shaped definition array (as CollectionHelper consumers expect)
     * from an element collection's stored row schema.
     *
     * @return array{title: string, titleList: string, key: string, description: string, hasPage: bool, fields: array<int, array<string, mixed>>}
     */
    public function schemaFor(Collection $elementCollection): array
    {
        return [
            'title' => (string) $elementCollection->name,
            'titleList' => (string) $elementCollection->name,
            'key' => (string) $elementCollection->collection_key,
            'description' => '',
            'hasPage' => false,
            'fields' => $elementCollection->element_fields ?? [],
        ];
    }

    /**
     * Human-readable name for an element collection, e.g. "Triggers Strategie & Konzept".
     * The owner name is the entry name (page owner) or the element title (element owner).
     */
    public function displayName(string $fieldLabel, string $ownerName): string
    {
        $label = mb_trim((string) (preg_split('/[—–-]/u', $fieldLabel)[0] ?? $fieldLabel));

        return mb_trim($label.' '.$ownerName);
    }

    /**
     * Strip the "detailData." prefix that field paths carry in YAML definitions.
     */
    private function normalizeFieldName(string $field): string
    {
        return str_replace('detailData.', '', $field);
    }

    /**
     * Normalize row field definitions to the YAML-resolved shape: names carry the
     * "detailData." prefix (consumers strip it themselves), with label/type/colspan defaults.
     *
     * @param  array<int, array<string, mixed>>  $rowFields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRowFields(array $rowFields): array
    {
        return array_values(array_map(function (array $field): array {
            $name = $this->normalizeFieldName((string) ($field['name'] ?? ''));

            return array_merge($field, [
                'name' => 'detailData.'.$name,
                'label' => (string) ($field['label'] ?? ''),
                'type' => (string) ($field['type'] ?? 'text'),
                'colspan' => (int) ($field['colspan'] ?? 12),
            ]);
        }, $rowFields));
    }
}
