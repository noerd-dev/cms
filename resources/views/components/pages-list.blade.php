<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdList;

new class extends Component {
    use LanguageFilterTrait;
    use NoerdList;

    public $listModel = Page::class;

    public ?string $detailRoute = 'cms.page.detail';

    public $detailComponent = 'cms::page-detail';

    public function mount(): void
    {
        $this->mountList();
        $this->ensureDefaultLanguage();

        if (empty($this->listFilters['language'])) {
            $this->listFilters['language'] = session('selectedLanguage');
        }

        $this->openCollectionFromRequest();
    }

    /**
     * Deep link: /cms/pages?collection={id|key}[&entry={id}] opens the matching
     * collection entries list (and optionally one element-collection entry).
     */
    private function openCollectionFromRequest(): void
    {
        $collectionParam = (string) request()->collection;
        if ($collectionParam === '') {
            return;
        }

        $collection = is_numeric($collectionParam)
            ? Collection::query()->find((int) $collectionParam)
            : Collection::query()->where('collection_key', mb_strtoupper($collectionParam))->first();

        if ($collection === null) {
            return;
        }

        Noerd::modal('cms::collection-entries-list', [
            'collectionKey' => $collection->id,
            'elementCollection' => (bool) $collection->is_element_collection,
        ]);

        $entryId = (int) request()->entry;
        if ($entryId && $collection->is_element_collection && Page::query()->where('collection_id', $collection->id)->whereKey($entryId)->exists()) {
            Noerd::modal('cms::element-collection-row-detail', [
                'modelId' => $entryId,
                'collectionKey' => strtolower($collection->collection_key),
            ]);
        }
    }

    #[Computed]
    public function tableFilters(): array
    {
        $filters = [];

        if ($this->hasMultipleLanguages()) {
            $filters[] = $this->getLanguageListFilter();
        }

        $filters[] = [
            'label' => __('Page Type'),
            'column' => 'page_type',
            'type' => 'Picklist',
            'options' => [
                '' => __('All Pages'),
                'collection' => __('Collection Pages'),
                'single' => __('Single Pages'),
            ],
        ];

        return $filters;
    }

    public function storeActiveListFilters(): void
    {
        session(['listFilters' => $this->listFilters]);

        // Sync with selectedLanguage for page-detail consistency
        if (! empty($this->listFilters['language'])) {
            session(['selectedLanguage' => $this->listFilters['language']]);
        }

        $this->resetPage();
    }

    public function listData(): array
    {
        // Get all collections with hasPage: false to exclude their pages
        $collectionsWithoutPages = Collection::query()
            ->get()
            ->filter(function ($collection) {
                $collectionFields = CollectionHelper::getCollectionFields(strtolower($collection->collection_key));

                return $collectionFields === null || ! ($collectionFields['hasPage'] ?? true);
            })
            ->pluck('id')
            ->toArray();

        // listQuery() applies search, sort and the YAML column filters — the
        // page-specific constraints are chained on top.
        $rows = $this->listQuery($this->listModel)
            ->with('collection')
            ->where(function ($query) use ($collectionsWithoutPages) {
                // Show pages that don't belong to any collection
                $query->whereNull('collection_id')
                    // OR pages that belong to collections with hasPage: true (exclude hasPage: false collections)
                    ->orWhereNotIn('collection_id', $collectionsWithoutPages);
            })
            ->when($this->listFilters['page_type'] ?? null, function ($query, $pageType): void {
                if ($pageType === 'collection') {
                    $query->whereNotNull('collection_id');
                } elseif ($pageType === 'single') {
                    $query->whereNull('collection_id');
                }
            })
            ->paginate($this->perPage);

        // Parse JSON attributes to show only current language values
        $selectedLanguage = $this->listFilters['language']
            ?? session('selectedLanguage');

        foreach ($rows->items() as $row) {
            if (is_array($row->name)) {
                $row->name = $row->name[$selectedLanguage] ?? array_values($row->name)[0] ?? '';
            }
            if (is_array($row->slug)) {
                $row->slug = $row->slug[$selectedLanguage] ?? array_values($row->slug)[0] ?? '';
            }
            $row->collection_name = $row->collection?->name ?? '';
        }

        return $this->buildList($rows);
    }

} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
