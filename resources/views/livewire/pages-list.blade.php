<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\Noerd;

new class extends Component
{
    use LanguageFilterTrait;
    use Noerd;

    public const COMPONENT = 'pages-list';

    protected const ALLOWED_TABLE_FILTERS = ['language'];

    public function mount(): void
    {
        $this->tableId = Str::random();
        $this->loadActiveTableFilters();
        $this->ensureDefaultLanguage();
    }

    #[Computed]
    public function tableFilters(): array
    {
        if (! $this->hasMultipleLanguages()) {
            return [];
        }

        return [$this->getLanguageFilter()];
    }

    public function storeActiveTableFilters(): void
    {
        session(['activeTableFilters' => $this->activeTableFilters]);

        // Sync with selectedLanguage for page-detail consistency
        if (! empty($this->activeTableFilters['language'])) {
            session(['selectedLanguage' => $this->activeTableFilters['language']]);
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'page-detail',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with()
    {
        // Get all collections with hasPage: false to exclude their pages
        $collectionsWithoutPages = Collection::where('tenant_id', Auth::user()->selected_tenant_id)
            ->get()
            ->filter(function ($collection) {
                $collectionFields = CollectionHelper::getCollectionFields(strtolower($collection->collection_key));

                return ! ($collectionFields['hasPage'] ?? true);
            })
            ->pluck('id')
            ->toArray();

        $rows = Page::where('tenant_id', Auth::user()->selected_tenant_id)
            ->where(function ($query) use ($collectionsWithoutPages) {
                // Show pages that don't belong to any collection
                $query->whereNull('collection_id')
                    // OR pages that belong to collections with hasPage: true (exclude hasPage: false collections)
                    ->orWhereNotIn('collection_id', $collectionsWithoutPages);
            })
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%');
                });
            })
            ->paginate(self::PAGINATION);

        // Parse JSON attributes to show only current language values
        $selectedLanguage = $this->activeTableFilters['language']
            ?? session('selectedLanguage');

        foreach ($rows->items() as $row) {
            if (is_array($row->name)) {
                $row->name = $row->name[$selectedLanguage] ?? array_values($row->name)[0] ?? '';
            }
            if (is_array($row->slug)) {
                $row->slug = $row->slug[$selectedLanguage] ?? array_values($row->slug)[0] ?? '';
            }
        }

        $tableConfig = StaticConfigHelper::getTableConfig('pages-list');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }

    public function rendering()
    {
        $this->loadActiveTableFilters();

        // Sync selectedLanguage with activeTableFilters
        if (empty($this->activeTableFilters['language'])) {
            $this->activeTableFilters['language'] = session('selectedLanguage');
        }

        if ((int) request()->pageId) {
            $this->tableAction(request()->pageId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
</x-noerd::page>
