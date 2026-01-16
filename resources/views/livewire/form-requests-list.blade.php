<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Website\Models\FormRequest;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'form-requests-list';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'form-request-detail',
            source: self::COMPONENT,
            arguments: ['formRequestId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $rows = FormRequest::paginate(self::PAGINATION);

        $tableConfig = $this->getTableConfig();

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }

    public function rendering()
    {
        if ((int)request()->formRequestId) {
            $this->tableAction(request()->formRequestId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <div>
        @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
    </div>
</x-noerd::page>
















