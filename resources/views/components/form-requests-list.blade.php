<?php

use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdList;
use Noerd\Cms\Models\FormRequest;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('cms::form-request-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        $rows = $this->listQuery(FormRequest::class)->paginate($this->perPage);

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

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>














