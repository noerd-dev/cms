<?php

use Livewire\Component;
use Noerd\Cms\Models\Author;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;

    public ?string $detailPrimary = 'authorId';

    public $detailModel = Author::class;
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ $pageLayout['title'] ?? __('Author') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)" />
    </x-slot:footer>
</x-noerd::page>
