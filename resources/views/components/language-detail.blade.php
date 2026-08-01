<?php

use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'cmsLanguageId';

    public $detailModel = CmsLanguage::class;

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

        $this->storeProcess($cmsLanguage);
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

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Language') }}</x-noerd::modal-title>
    </x-slot:header>

    @php($pageLayout = StaticConfigHelper::getComponentFields('language-detail'))
    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$modelId"/>
    </x-slot:footer>
</x-noerd::page>
