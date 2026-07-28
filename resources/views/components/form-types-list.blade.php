<?php

use Livewire\Component;
use Noerd\Cms\Models\FormType;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('cms::form-type-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        $rows = $this->listQuery(FormType::class)->paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        if ((int) request()->formTypeId) {
            $this->listAction(request()->formTypeId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
