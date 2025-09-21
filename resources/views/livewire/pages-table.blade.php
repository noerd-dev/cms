<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'pages-table';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'page-component',
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
                return !($collectionFields['hasPage'] ?? true);
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
                    $query->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(self::PAGINATION);

        // Parse JSON attributes to show only current language values
        foreach ($rows->items() as $row) {
            if (is_array($row->name)) {
                $row->name = $row->name[session('selectedLanguage')] ?? array_values($row->name)[0] ?? '';
            }
            if (is_array($row->slug)) {
                $row->slug = $row->slug[session('selectedLanguage')] ?? array_values($row->slug)[0] ?? '';
            }
        }

        $tableConfig = StaticConfigHelper::getTableConfig('pages-table');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }

    public function rendering()
    {
        if ((int)request()->pageId) {
            $this->tableAction(request()->pageId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Pages') }}</x-noerd::modal-title>
    </x-slot:header>

    <div>
        @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
    </div>
</x-noerd::page>
