<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\PageLayouts;

/**
 * Persists collection entries (pages of a collection) and the rows of element
 * collections from the editors' form state — shared by the page editor and
 * the element-collection row editor.
 */
final class CollectionEntryStore
{
    public function __construct(
        private readonly CollectionDefinitionRepositoryContract $definitions,
        private readonly PageSlugService $slugs,
    ) {}

    /**
     * Field names of a resolved layout without the `detailData.` prefix.
     *
     * @param  array<string, mixed>|null  $layout
     * @return array<int, string>
     */
    public function fieldNames(?array $layout): array
    {
        $names = [];

        foreach ($layout['fields'] ?? [] as $field) {
            $name = str_replace('detailData.', '', (string) ($field['name'] ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Only the layout's fields out of the form state.
     *
     * @param  array<string, mixed>|null  $layout
     * @param  array<string, mixed>  $detailData
     * @return array<string, mixed>
     */
    public function extract(?array $layout, array $detailData): array
    {
        $data = [];

        foreach ($this->fieldNames($layout) as $name) {
            if (array_key_exists($name, $detailData)) {
                $data[$name] = $detailData[$name];
            }
        }

        return $data;
    }

    /**
     * The collection row an entry belongs to, created on first use. The
     * definition's key is authoritative: URL keys are hyphenated filenames,
     * while definition keys may use underscores — uppercasing the filename
     * would create an empty duplicate row next to the real one.
     */
    public function parentCollection(string $collectionKey, int $tenantId, ?int $createdBy): Collection
    {
        $definition = $this->definitions->find($collectionKey);

        return Collection::firstOrCreate([
            'tenant_id' => $tenantId,
            'collection_key' => $definition?->key ?: mb_strtoupper($collectionKey),
        ], [
            'name' => $definition?->titleList ?: ucfirst($collectionKey),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Validation errors for an entry with page features (name and slug of the
     * default language are required), keyed by the form field.
     *
     * @param  array<string, mixed>  $detailData
     * @return array<string, string>
     */
    public function requiredFieldErrors(array $detailData, string $defaultLanguageCode): array
    {
        $errors = [];

        if (empty($detailData['name'][$defaultLanguageCode] ?? '')) {
            $errors['detailData.name'] = __('validation.required', ['attribute' => __('Title')]);
        }

        if (empty($detailData['slug'][$defaultLanguageCode] ?? '')) {
            $errors['detailData.slug'] = __('validation.required', ['attribute' => __('URL')]);
        }

        return $errors;
    }

    /**
     * Create or update a collection entry from the editor's form state. The
     * caller validates first (requiredFieldErrors()) for entries with pages.
     *
     * @param  array<string, mixed>|null  $layout
     * @param  array<string, mixed>  $detailData
     */
    public function persist(
        string $collectionKey,
        ?array $layout,
        array $detailData,
        ?int $modelId,
        int $tenantId,
        ?int $createdBy,
        string $defaultLanguageCode,
    ): Page {
        $parentCollection = $this->parentCollection($collectionKey, $tenantId, $createdBy);
        $hasPageFeatures = $layout['hasPage'] ?? true;

        $data = [
            'tenant_id' => $tenantId,
            'collection_id' => $parentCollection->id,
            'data' => FieldTypeConverter::convertCollectionData($this->extract($layout, $detailData), $collectionKey),
            'sort' => (int) ($detailData['sort'] ?? 0),
            'layout' => $detailData['layout'] ?? PageLayouts::default(),
            'is_active' => true,
            'name' => null,
            'slug' => null,
        ];

        if ($hasPageFeatures) {
            $names = array_filter(is_array($detailData['name'] ?? null) ? $detailData['name'] : [], fn($value): bool => ! empty($value));

            $slugs = [];
            foreach (is_array($detailData['slug'] ?? null) ? $detailData['slug'] : [] as $language => $slug) {
                if (! empty($slug)) {
                    $slugs[$language] = $slug;
                } elseif (! empty($names[$language] ?? '')) {
                    $slugs[$language] = $this->slugs->uniqueFor($names[$language], (string) $language, $defaultLanguageCode, $tenantId, $modelId);
                }
            }

            $data['name'] = $names;
            $data['slug'] = $slugs;
        }

        return $modelId
            ? Page::updateOrCreate(['id' => $modelId], $data)
            : Page::create($data);
    }

    /**
     * Create or update a row of an element collection. New rows are appended
     * after the existing ones.
     *
     * @param  array<string, mixed>|null  $layout
     * @param  array<string, mixed>  $detailData
     */
    public function persistRow(Collection $elementCollection, ?array $layout, array $detailData, ?int $modelId): Page
    {
        $attributes = [
            'tenant_id' => $elementCollection->tenant_id,
            'collection_id' => $elementCollection->id,
            'data' => $this->extract($layout, $detailData),
            'is_active' => true,
        ];

        if ($modelId) {
            $attributes['sort'] = (int) ($detailData['sort'] ?? 0);

            return Page::updateOrCreate(['id' => $modelId], $attributes);
        }

        $attributes['sort'] = (int) ($elementCollection->rows()->max('sort') ?? -1) + 1;

        return Page::create($attributes);
    }

    /**
     * Insert a copy of the row directly after the original.
     */
    public function duplicateRow(Page $row): Page
    {
        Page::query()
            ->where('collection_id', $row->collection_id)
            ->where('sort', '>', $row->sort ?? 0)
            ->increment('sort');

        $copy = $row->replicate(['id']);
        $copy->sort = ($row->sort ?? 0) + 1;
        $copy->save();

        return $copy;
    }
}
