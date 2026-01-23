<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Noerd\Scopes\SearchScope;
use Noerd\Noerd\Scopes\TenantScope;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {
    use LanguageFilterTrait;
    use Noerd;

    public const COMPONENT = 'pages-list';

    protected const ALLOWED_TABLE_FILTERS = ['language'];

    public function mount(): void
    {
        $this->listId = Str::random();
        $this->loadActiveListFilters();
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

    public function storeActiveListFilters(): void
    {
        session(['activeListFilters' => $this->activeListFilters]);

        // Sync with selectedLanguage for page-detail consistency
        if (! empty($this->activeListFilters['language'])) {
            session(['selectedLanguage' => $this->activeListFilters['language']]);
        }
    }

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'page-detail',
            source: self::COMPONENT,
            arguments: ['pageId' => $modelId, 'relationId' => $relationId],
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

                return ! ($collectionFields['hasPage'] ?? true);
            })
            ->pluck('id')
            ->toArray();

        // Disable SearchScope since we need custom JSON search for translatable fields
        $rows = Page::withoutGlobalScope(SearchScope::class)
            ->where(function ($query) use ($collectionsWithoutPages) {
                // Show pages that don't belong to any collection
                $query->whereNull('collection_id')
                    // OR pages that belong to collections with hasPage: true (exclude hasPage: false collections)
                    ->orWhereNotIn('collection_id', $collectionsWithoutPages);
            })
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%');
                });
            })
            ->paginate(self::PAGINATION);

        // Parse JSON attributes to show only current language values
        $selectedLanguage = $this->activeListFilters['language']
            ?? session('selectedLanguage');

        foreach ($rows->items() as $row) {
            if (is_array($row->name)) {
                $row->name = $row->name[$selectedLanguage] ?? array_values($row->name)[0] ?? '';
            }
            if (is_array($row->slug)) {
                $row->slug = $row->slug[$selectedLanguage] ?? array_values($row->slug)[0] ?? '';
            }
        }

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        $this->loadActiveListFilters();

        // Sync selectedLanguage with activeListFilters
        if (empty($this->activeListFilters['language'])) {
            $this->activeListFilters['language'] = session('selectedLanguage');
        }

        if ((int)request()->pageId) {
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
