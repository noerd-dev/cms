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
use Noerd\Scopes\TenantScope;
use Noerd\Traits\NoerdList;

new class extends Component {
    use LanguageFilterTrait;
    use NoerdList;

    public function mount(): void
    {
        $this->listId = Str::random();
        $this->loadListFilters();
        $this->ensureDefaultLanguage();
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
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('cms::page-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        // Get all collections with hasPage: false to exclude their pages
        $collectionsWithoutPages = Collection::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->selected_tenant_id)
            ->get()
            ->filter(function ($collection) {
                $collectionFields = CollectionHelper::getCollectionFields(strtolower($collection->collection_key));

                return $collectionFields === null || ! ($collectionFields['hasPage'] ?? true);
            })
            ->pluck('id')
            ->toArray();

        $rows = Page::with('collection')
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
            ->when($this->search, function ($query): void {
                $languages = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
                    ->where('is_active', true)
                    ->pluck('code');

                $search = mb_strtolower($this->search);
                $query->where(function ($query) use ($languages, $search): void {
                    foreach ($languages as $code) {
                        $query->orWhereRaw(
                            'LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, ?))) LIKE ?',
                            ['$.'.$code, '%'.$search.'%']
                        );
                    }
                });
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

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        $this->loadListFilters();

        // Sync selectedLanguage with listFilters
        if (empty($this->listFilters['language'])) {
            $this->listFilters['language'] = session('selectedLanguage');
        }

        if ((int) request()->pageId) {
            $this->listAction(request()->pageId);
        }

        $collectionParam = (string) request()->collection;
        if ($collectionParam !== '') {
            $collection = is_numeric($collectionParam)
                ? Collection::query()->find((int) $collectionParam)
                : Collection::query()->where('collection_key', mb_strtoupper($collectionParam))->first();

            if ($collection !== null) {
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
        }

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
