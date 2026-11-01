<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Facades\Noerd;
use Noerd\Support\ListFilter;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Traits\NoerdList;

new class extends Component {
    use LanguageFilterTrait;
    use NoerdList;

    public $listModel = Navigation::class;

    public ?string $detailRoute = 'cms.navigation.detail';

    public $detailComponent = 'cms::navigation-detail';

    protected array $listWith = ['page.content'];

    public function mount(): void
    {
        $this->mountList();

        if (empty($this->listFilters['language'])) {
            $this->listFilters['language'] = session('selectedLanguage') ?: $this->defaultLanguageCode();
        }

        if (empty(session('selectedLanguage'))) {
            session(['selectedLanguage' => $this->listFilters['language']]);
        }
    }

    #[Computed]
    public function tableFilters(): array
    {
        $filters = [];

        if ($this->hasMultipleLanguages()) {
            $filters[] = $this->getLanguageListFilter();
        }

        $filters[] = $this->getNavigationKeyListFilter();

        return $filters;
    }

    public function storeActiveListFilters(): void
    {
        session(['listFilters' => $this->listFilters]);

        if (! empty($this->listFilters['language'])) {
            session(['selectedLanguage' => $this->listFilters['language']]);
        }

        $this->resetPage();
    }

    public function createSubNav(mixed $parentId): void
    {
        $parent = Navigation::find($parentId);
        if (! $parent || $parent->parent_id) {
            return;
        }

        Noerd::modalFor('cms.navigation.detail', 'cms::navigation-detail', ['modelId' => null, 'relations' => ['parent_id' => $parentId]]);
    }

    public function listData(): array
    {
        // listQuery() applies search and the read guard; the hierarchical
        // ordering below requires in-memory assembly and manual pagination.
        $allItems = $this->listQuery($this->listModel)
            ->reorder('sort_order')
            ->get();

        // Build hierarchical flat list: parent followed by its children
        $topLevel = $allItems->whereNull('parent_id');
        $childrenGrouped = $allItems->whereNotNull('parent_id')->groupBy('parent_id');

        $sorted = collect();
        foreach ($topLevel as $parent) {
            $sorted->push($parent);
            if ($childrenGrouped->has($parent->id)) {
                foreach ($childrenGrouped[$parent->id] as $child) {
                    $sorted->push($child);
                }
            }
        }

        // Append orphaned children (parent not in current result set)
        foreach ($childrenGrouped as $parentId => $children) {
            if (! $topLevel->contains('id', $parentId)) {
                foreach ($children as $child) {
                    $sorted->push($child);
                }
            }
        }

        // Manual pagination
        $page = LengthAwarePaginator::resolveCurrentPage();
        $slice = $sorted->slice(($page - 1) * $this->perPage, $this->perPage)->values();
        $rows = new LengthAwarePaginator($slice, $sorted->count(), $this->perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        $selectedLanguage = $this->listFilters['language']
            ?? session('selectedLanguage')
            ?? $this->defaultLanguageCode();

        // decode name json for table output per selected language
        foreach ($rows as $row) {
            $row->page_name = RelationFieldDefinition::normalizeDisplayValue($row->page?->name) ?: '';
            $oldName = $row->name;
            $decoded = is_string($row->name) ? json_decode($row->name, true) : ($row->name ?? []);
            $displayName = $decoded[$selectedLanguage] ?? array_values($decoded)[0] ?? $oldName;

            if ($row->parent_id) {
                $row->name = [
                    'prefix' => '↳ ',
                    'prefixClass' => 'opacity-50',
                    'text' => $displayName,
                ];
            } else {
                $row->name = $displayName;
            }
        }

        return $this->buildList($rows);
    }

    /**
     * One option per navigation group the tenant uses; listQuery() narrows the
     * real `navigation_key` column itself.
     */
    protected function getNavigationKeyListFilter(): ListFilter
    {
        return ListFilter::options(__('Navigation Key'), 'navigation_key', fn(): array => Navigation::distinct()
            ->orderBy('navigation_key')
            ->pluck('navigation_key', 'navigation_key')
            ->all());
    }

}; ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
