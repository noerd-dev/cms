<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Noerd\Cms\Models\FormRequest;

new class extends Component {
    use NoerdList;

    public $listModel = FormRequest::class;

    public $detailComponent = 'cms::form-request-page';

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














