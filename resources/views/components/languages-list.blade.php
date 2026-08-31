<?php

use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = CmsLanguage::class;

    public ?string $detailRoute = 'cms.language.detail';

    public $detailComponent = 'cms::language-detail';

    protected function getDeepLinkParam(): string
    {
        // Matches language-detail's $detailPrimary URL alias.
        return 'cmsLanguageId';
    }
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
