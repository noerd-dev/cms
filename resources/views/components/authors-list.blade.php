<?php

use Livewire\Component;
use Noerd\Cms\Models\Author;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use NoerdList;

    public $listModel = Author::class;

    public ?string $detailRoute = 'cms.author.detail';


    public function rendering()
    {
        $this->loadListFilters();

        if ((int) request()->authorId) {
            $this->listAction(request()->authorId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
