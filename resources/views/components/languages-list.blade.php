<?php

use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = CmsLanguage::class;

    public ?string $detailRoute = 'cms.language.detail';


    public function rendering()
    {
        if ((int) request()->cmsLanguageId) {
            $this->listAction(request()->cmsLanguageId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
