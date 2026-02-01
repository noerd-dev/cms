<?php

use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const DETAIL_COMPONENT = 'cms-language-detail';
    public const LIST_COMPONENT = 'cms-languages-list';
    public const ID = 'cmsLanguageId';
    #[\Livewire\Attributes\Url(keep: false, except: '')]
    public ?string $cmsLanguageId = null;

    public array $cmsLanguageData = [];

    public function mount(CmsLanguage $cmsLanguage): void
    {
        if ($this->cmsLanguageId) {
            $cmsLanguage = CmsLanguage::find($this->cmsLanguageId);
        }

        $this->mountModalProcess(self::DETAIL_COMPONENT, $cmsLanguage);
        $this->cmsLanguageData = $cmsLanguage->toArray();
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $data = $this->cmsLanguageData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;

        // Model events handle is_default consistency
        $cmsLanguage = CmsLanguage::updateOrCreate(['id' => $this->cmsLanguageId], $data);

        $this->storeProcess($cmsLanguage);
    }

    public function delete(): void
    {
        $cmsLanguage = CmsLanguage::find($this->cmsLanguageId);
        if ($cmsLanguage) {
            $cmsLanguage->delete();
        }
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('cms_label_language') }}</x-noerd::modal-title>
    </x-slot:header>

    @php($pageLayout = StaticConfigHelper::getComponentFields('cms-language-detail'))
    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$cmsLanguageId"/>
    </x-slot:footer>
</x-noerd::page>
