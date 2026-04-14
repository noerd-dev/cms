<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    #[Url(as: 'globalParameterId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = GlobalParameter::class;

    public function mount(): void
    {
        $this->initDetail();

        $globalParameter = new GlobalParameter;
        if ($this->modelId) {
            $globalParameter = GlobalParameter::find($this->modelId) ?? new GlobalParameter;
        }

        $this->detailData = $globalParameter->toArray();

        // Normalize value for editing: decode JSON into PHP value (string or array)
        if (isset($this->detailData['value']) && is_string($this->detailData['value'])) {
            $decoded = json_decode($this->detailData['value'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->detailData['value'] = $decoded;
            }
        }
    }

    public function store(): void
    {
        $this->validate([
            'detailData.key' => ['required', 'string', 'max:255'],
            'detailData.value' => ['required'],
        ]);

        $data = $this->detailData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;
        // auto detect if value is an array and convert it to JSON; if string, encode plain string
        $value = $this->detailData['value'];
        // If array with languages, keep as is; else wrap in current language if available
        if (is_array($value)) {
            $data['value'] = json_encode($value);
        } else {
            $data['value'] = json_encode((string) $value);
        }
        $globalParameter = GlobalParameter::updateOrCreate(['id' => $this->modelId], $data);

        $this->dispatch('storeElements');
        $this->storeProcess($globalParameter);
    }

    public function delete(): void
    {
        $globalParameter = GlobalParameter::find($this->modelId);
        $globalParameter->delete();
        $this->closeModalProcess($this->getListComponent());
    }

    #[On('languageChanged')]
    public function refresh()
    {
        $this->dispatch('$refresh');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ __('Global Parameter') }}

            <div class="ml-auto" :class="isModal ? 'mr-22' : ''">
                <div class="flex bg-white p-1 rounded-lg w-fit border border-gray-200">
                    <livewire:language-switcher/>
                </div>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false && isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
