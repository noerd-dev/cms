<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Noerd\Cms\Models\FormRequest;

new class extends Component {
    use NoerdList;

    public $listModel = FormRequest::class;

    public ?string $detailRoute = 'cms.form-request.detail';


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














