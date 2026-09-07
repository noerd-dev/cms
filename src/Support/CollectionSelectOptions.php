<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Support\RelationFieldDefinition;

/**
 * Option lists of the CMS select field types (collection-select,
 * homepage-select), resolved by the field type resolvers so the templates stay
 * query-free and can render through the active theme's select element.
 */
final class CollectionSelectOptions
{
    /**
     * The tenant's regular collections, optionally narrowed to those whose
     * definition declares every one of the given field names.
     *
     * @param  array<int, string>  $requiredFields
     * @return array<int, array{value: int, label: string}>
     */
    public static function collections(array $requiredFields = []): array
    {
        return Collection::query()
            ->where('is_element_collection', false)
            ->orderBy('name')
            ->get()
            ->filter(function (Collection $collection) use ($requiredFields): bool {
                if ($requiredFields === []) {
                    return true;
                }

                $fieldNames = CollectionHelper::getCollectionFieldNames((string) $collection->collection_key);

                return array_diff($requiredFields, $fieldNames) === [];
            })
            ->map(fn(Collection $collection): array => [
                'value' => $collection->id,
                'label' => (string) $collection->name,
            ])
            ->values()
            ->all();
    }

    /**
     * The tenant's plain pages (no collection entries), by display name.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function pages(): array
    {
        return Page::query()
            ->whereNull('collection_id')
            ->orderBy('name')
            ->get()
            ->map(fn(Page $page): array => [
                'value' => $page->id,
                'label' => RelationFieldDefinition::normalizeDisplayValue($page->name),
            ])
            ->values()
            ->all();
    }
}
