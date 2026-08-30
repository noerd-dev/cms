<?php

use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'cmsLanguageId';

    public $detailModel = CmsLanguage::class;
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Language') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
