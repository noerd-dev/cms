<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Noerd\Cms\Models\Page;
use Noerd\Support\RelationFieldDefinition;

/**
 * Option lists of the CMS select field types (`homepageSelect`), resolved by
 * the field type resolvers so the templates stay query-free and can render
 * through the active theme's select element.
 */
final class CollectionSelectOptions
{
    /**
     * The tenant's plain pages (no collection entries), by display name.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function pages(): array
    {
        return Page::query()
            ->with('content')
            ->whereNull('collection_id')
            ->get()
            ->map(fn(Page $page): array => [
                'value' => $page->id,
                'label' => RelationFieldDefinition::normalizeDisplayValue($page->name),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
