<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Traits\LanguageFilterTrait;
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
            'label' => __('cms_label_page_type'),
            'column' => 'page_type',
            'type' => 'Picklist',
            'options' => [
                '' => __('cms_all_pages'),
                'collection' => __('cms_collection_pages'),
                'single' => __('cms_single_pages'),
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
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'page-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with()
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
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%');
                });
            })
            ->paginate(self::PAGINATION);

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

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
