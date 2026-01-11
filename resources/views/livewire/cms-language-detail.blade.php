<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'cms-language-detail';
    public const LIST_COMPONENT = 'cms-languages-list';
    public const ID = 'cmsLanguageId';
    #[\Livewire\Attributes\Url(keep: false, except: '')]
    public ?string $cmsLanguageId = null;

    public array $model;
    public CmsLanguage $cmsLanguage;

    public function mount(CmsLanguage $model): void
    {
        if ($this->modelId) {
            $model = CmsLanguage::find($this->modelId);
        }

        $this->mountModalProcess(self::COMPONENT, $model);
        $this->cmsLanguage = $model;
    }

    public function store(): void
    {
        $this->validateFromLayout();

        $data = $this->model;
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
