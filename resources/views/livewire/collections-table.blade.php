<?php

use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Noerd\Helpers\StaticConfigHelper;

new class extends Component {

    use Noerd;
    public const COMPONENT = 'collections-table';

    public $tableLayout;

    #[Url]
    public ?string $key = null;

    public function mount()
    {
        if (!$this->key) {
            abort(404);
        }

        $this->tableLayout = CollectionHelper::getCollectionTable($this->key);
        $this->pageLayout = CollectionHelper::getCollectionFields($this->key);

        if ((int)request()->customerId) {
            $this->tableAction(request()->customerId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'page-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId, 'collectionKey' => $this->key],
        );
    }

    public function with(): array
    {
        // Find the collection by key and tenant
        $collection = Collection::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('collection_key', strtoupper($this->key))
            ->first();

        if (!$collection) {
            $pages = collect();
        } else {
            $pages = Page::where('collection_id', $collection->id)
                ->where('tenant_id', auth()->user()->selected_tenant_id)
                ->orderBy('sort')
                ->paginate(self::PAGINATION);
        }

        $rows = [];
        foreach ($pages as $page) {
            $row = $page->data ?? [];
            $row['id'] = $page->id;

            $rows[] = $row;
        }

        $arrayRows = [];
        foreach ($rows as $columns) {
            $row = [];
            foreach ($columns as $key => $column) {
                if(is_array($column)) {
                    $value = $column[session('selectedLanguage')] ?? array_values($column)[0] ?? '';
                }
                else {
                    $value = $column;
                }
                $row[$key] = $value;
            }

            $arrayRows[] = $row;
        }

        $tableConfig = StaticConfigHelper::getTableConfig('collections-table');

        return [
            'rows' => $arrayRows,
            'tableConfig' => $tableConfig,
        ];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig, 'table' => $tableLayout])
</x-noerd::page>
