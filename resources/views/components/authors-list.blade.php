<?php

use Livewire\Component;
use Noerd\Cms\Models\Author;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use NoerdList;

    public $listModel = Author::class;

    public ?string $detailRoute = 'cms.author.detail';

    public $detailComponent = 'cms::author-detail';
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
