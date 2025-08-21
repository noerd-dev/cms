<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Noerd\Cms\Models\Navigation;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Noerd\Helpers\StaticConfigHelper;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'navigation-table';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'navigation-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $rows = Navigation::where('tenant_id', Auth::user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('navigation_key', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(self::PAGINATION);

        // decode name json for table output per selected language
        foreach ($rows as $row) {
            $oldName = $row->name;
            $decoded = is_string($row->name) ? json_decode($row->name, true) : ($row->name ?? []);
            $row->name = $decoded[session('selectedLanguage')] ?? array_values($decoded)[0] ?? $oldName;
        }

        $tableConfig = StaticConfigHelper::getTableConfig('navigation-table');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }

    public function rendering()
    {
        if ((int)request()->navigationId) {
            $this->tableAction(request()->navigationId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }
}; ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
</x-noerd::page>


