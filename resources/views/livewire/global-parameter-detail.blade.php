<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const DETAIL_COMPONENT = 'global-parameter-detail';
    public const LIST_COMPONENT = 'global-parameters-list';
    public const ID = 'globalParameterId';
    #[Url(keep: false, except: '')]
    public $globalParameterId = null;

    public array $globalParameterData = [];

    public function mount(GlobalParameter $globalParameter): void
    {
        if ($this->globalParameterId) {
            $globalParameter = GlobalParameter::find($this->globalParameterId);
        }

        $this->mountModalProcess(self::DETAIL_COMPONENT, $globalParameter);
        $this->globalParameterData = $globalParameter->toArray();

        // Normalize value for editing: decode JSON into PHP value (string or array)
        if (isset($this->globalParameterData['value']) && is_string($this->globalParameterData['value'])) {
            $decoded = json_decode($this->globalParameterData['value'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->globalParameterData['value'] = $decoded;
            }
        }
    }

    public function store(): void
    {
        $this->validate([
            'globalParameterData.key' => ['required', 'string', 'max:255'],
            'globalParameterData.value' => ['required'],
        ]);

        $data = $this->globalParameterData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;
        // auto detect if value is an array and convert it to JSON; if string, encode plain string
        $value = $this->globalParameterData['value'];
        // If array with languages, keep as is; else wrap in current language if available
        if (is_array($value)) {
            $data['value'] = json_encode($value);
        } else {
            $data['value'] = json_encode((string) $value);
        }
        $globalParameter = GlobalParameter::updateOrCreate(['id' => $this->globalParameterId], $data);

        $this->dispatch('storeElements');
        $this->showSuccessIndicator = true;

        if ($globalParameter->wasRecentlyCreated) {
            $this->globalParameterId = $globalParameter['id'];
        }
    }

    public function delete(): void
    {
        $globalParameter = GlobalParameter::find($this->globalParameterId);
        $globalParameter->delete();
        $this->closeModalProcess(self::LIST_COMPONENT);
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
            {{ __('cms_global_parameter') }}

            <div class="ml-auto" :class="isModal ? 'mr-22' : ''">
                <div class="flex bg-white p-1 rounded-lg w-fit border border-gray-200">
                    <livewire:language-switcher/>
                </div>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false && isset($globalParameterId)"/>
    </x-slot:footer>
</x-noerd::page>
