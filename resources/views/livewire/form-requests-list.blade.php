<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Website\Models\FormRequest;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'form-requests-list';

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'form-request-detail',
            source: self::COMPONENT,
            arguments: ['formRequestId' => $modelId, 'relationId' => $relationId],
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
        if ((int)request()->formRequestId) {
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














