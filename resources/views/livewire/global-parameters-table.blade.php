<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Noerd\Traits\NoerdTableTrait;

new class extends Component {

    use NoerdTableTrait;

    public const COMPONENT = 'global-parameters-table';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch('set-app-id', ['id' => null]);

        $this->dispatch(
            event: 'noerdModal',
            component: 'global-parameter-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with()
    {
        $rows = GlobalParameter::where('tenant_id', Auth::user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('value', 'like', '%' . $this->search . '%')
                        ->orWhere('key', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(self::PAGINATION);

        foreach ($rows as $row) {

            $oldName = $row->value;
            $row->value = json_decode($row->value, true);
            $row->value = $row->value[session('selectedLanguage')] ?? $row->value;

            if (strlen($row->value) == 0) {
                $row->value = $oldName;
            }
        }

        return [
            'rows' => $rows,
        ];
    }

    public function rendering()
    {
        if ((int)request()->globalParameterId) {
            $this->tableAction(request()->globalParameterId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <div>
        @include('noerd::components.table.table-build',
        [
            'title' => __('Globale Parameter'),
            'newLabel' => __('Neuer Parameter'),
            'redirectAction' => '',
            'table' => [
                [
                    'width' => 30,
                    'field' => 'key',
                    'label' => __('Schlüssel'),
                ],
                [
                    'width' => 30,
                    'field' => 'value',
                    'label' => __('Wert'),
                ],
            ],
        ])
    </div>
</x-noerd::page>
