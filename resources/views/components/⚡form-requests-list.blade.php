<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Noerd\Website\Models\FormRequest;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'form-request-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with(): array
    {
        $rows = FormRequest::paginate(self::PAGINATION);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        if ((int) request()->formRequestId) {
            $this->listAction(request()->formRequestId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>














