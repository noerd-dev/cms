<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Noerd\Helpers\StaticConfigHelper;

// Dieses Element wird aktuell nicht verwendet. [NF-1]
new class extends Component {
    use Noerd;

    public const COMPONENT = 'collection-select-modal';

    public $context = null;

    public function tableAction(mixed $modelId): void
    {
        $this->dispatch('collectionSelected', $modelId, $this->context);
        $this->dispatch('close-modal-' . self::COMPONENT);
    }

    public function with(): array
    {
        $rows = Collection::where('tenant_id', auth()->user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->withCount('rows')
            ->paginate(self::PAGINATION);

        $tableConfig = StaticConfigHelper::getTableConfig('collection-select-modal');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">

    <x-slot:header>
        <x-noerd::modal-title>{{ __('Select Page') }}</x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])

</x-noerd::page>








