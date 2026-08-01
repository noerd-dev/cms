<?php

use Livewire\Component;
use Noerd\Cms\Models\FormType;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = FormType::class;

    public ?string $detailRoute = 'cms.form-type.detail';


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
