<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\FormRequest;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Noerd\Helpers\StaticConfigHelper;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'form-requests-table';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'form-request-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $rows = FormRequest::where('tenant_id', auth()->user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('data', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(self::PAGINATION);

        $tableConfig = StaticConfigHelper::getTableConfig('form-requests-table');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <div>
        @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
    </div>
</x-noerd::page>


