<?php

use Livewire\Component;
use Noerd\Cms\Models\FormType;
use Noerd\Traits\Noerd;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'form-types-list';

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'form-type-detail',
            source: self::COMPONENT,
            arguments: ['formTypeId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with()
    {
        $rows = FormType::paginate(self::PAGINATION);

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

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
