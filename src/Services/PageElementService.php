<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Cms\Support\MediaValues;
use Noerd\Cms\Traits\HandlesPageElements;

class PageElementService
{
    use HandlesPageElements {
        localizeArray as protected handlesLocalizeArray;
    }

    /**
     * Decode a Page model's `data` JSON and resolve translatable arrays
     * for the given language. Used by collection-driven detail templates
     * (services, projects) that don't render via the element loop.
     *
     * @return array<string, mixed>
     */
    public function processCollectionPageData(Model $page, ?string $language = null): array
    {
        $language ??= CmsLanguageCodes::active()[0];

        $raw = $page->getRawOriginal('data');
        $data = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);

        $data = $this->injectElementCollections($page, $data);
        $data = $this->handlesLocalizeArray($data, $language);

        return MediaValues::resolve($data, $this->collectionFields($page));
    }


    /**
     * The field definition of the collection an entry belongs to — the source of
     * truth for which of its values are image fields. Unknown or page models
     * without a collection yield no fields, and every value is passed through.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectionFields(Model $page): array
    {
        if (! method_exists($page, 'collection')) {
            return [];
        }

        $key = $page->collection?->collection_key;

        if (blank($key)) {
            return [];
        }

        return CollectionHelper::getCollectionFields(mb_strtolower((string) $key))['fields'] ?? [];
    }

    /**
     * Merge the rows of any element collections owned by this entry back into its
     * data under their `owner_field` key, so frontend templates that used to read an
     * inline repeater (e.g. `triggers_items`) keep working unchanged.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function injectElementCollections(Model $page, array $data): array
    {
        if (! $page->getKey()) {
            return $data;
        }

        Collection::query()
            ->where('page_id', $page->getKey())
            ->where('is_element_collection', true)
            ->with('rows')
            ->get()
            ->each(function (Collection $elementCollection) use (&$data): void {
                if (! $elementCollection->owner_field) {
                    return;
                }

                $rowFields = $elementCollection->element_fields ?? [];

                $data[$elementCollection->owner_field] = $elementCollection->rows
                    ->map(fn($row) => is_array($row->data) ? $row->data : [])
                    ->map(fn(array $row): array => MediaValues::resolve($row, $rowFields))
                    ->all();
            });

        return $data;
    }

}
