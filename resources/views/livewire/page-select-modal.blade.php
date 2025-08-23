<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Noerd\Helpers\StaticConfigHelper;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'page-select-modal';

    public function tableAction(mixed $modelId): void
    {
        $this->dispatch('pageSelected', $modelId);
        $this->dispatch('close-modal-' . self::COMPONENT);
    }

    public function with(): array
    {
        $rows = Page::where('tenant_id', auth()->user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(self::PAGINATION);

        // decode name json for display
        foreach ($rows as $row) {
            $oldName = $row->name;
            $decoded = is_string($row->name) ? json_decode($row->name, true) : ($row->name ?? []);
            $row->name = $decoded[session('selectedLanguage')] ?? array_values($decoded)[0] ?? $oldName;
        }

        $tableConfig = StaticConfigHelper::getTableConfig('page-select-modal');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Seite auswählen') }}</x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])

</x-noerd::page>







