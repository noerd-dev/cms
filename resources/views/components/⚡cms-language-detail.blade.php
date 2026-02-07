<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    #[Url(as: 'cmsLanguageId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = CmsLanguage::class;

    public function mount(): void
    {
        $this->initDetail();
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $data = $this->detailData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;

        // Model events handle is_default consistency
        $cmsLanguage = CmsLanguage::updateOrCreate(['id' => $this->modelId], $data);

        $this->showSuccessIndicator = true;

        if ($cmsLanguage->wasRecentlyCreated) {
            $this->modelId = $cmsLanguage->id;
        }
    }

    public function delete(): void
    {
        $cmsLanguage = CmsLanguage::find($this->modelId);
        if ($cmsLanguage) {
            $cmsLanguage->delete();
        }
        $this->closeModalProcess($this->getListComponent());
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('cms_label_language') }}</x-noerd::modal-title>
    </x-slot:header>

    @php($pageLayout = StaticConfigHelper::getComponentFields('cms-language-detail'))
    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$modelId"/>
    </x-slot:footer>
</x-noerd::page>
