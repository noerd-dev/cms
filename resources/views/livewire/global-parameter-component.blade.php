<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'global-parameter-component';
    public const LIST_COMPONENT = 'global-parameters-table';
    public const ID = 'globalParameterId';
    #[Url(keep: false, except: '')]
    public ?string $globalParameterId = null;

    public array $model;
    public GlobalParameter $globalParameter;

    public function mount(GlobalParameter $model): void
    {
        if ($this->modelId) {
            $model = GlobalParameter::find($this->modelId);
        }

        $this->mountModalProcess(self::COMPONENT, $model);

        // Normalize value for editing: decode JSON into PHP value (string or array)
        if (isset($this->model['value']) && is_string($this->model['value'])) {
            $decoded = json_decode($this->model['value'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->model['value'] = $decoded;
            }
        }
    }

    public function store(): void
    {
        $this->validate([
            'model.key' => ['required', 'string', 'max:255'],
            'model.value' => ['required'],
        ]);

        $model = $this->model;
        $model['tenant_id'] = auth()->user()->selected_tenant_id;
        // auto detect if value is an array and convert it to JSON; if string, encode plain string
        $value = $this->model['value'];
        // If array with languages, keep as is; else wrap in current language if available
        if (is_array($value)) {
            $model['value'] = json_encode($value);
        } else {
            $model['value'] = json_encode((string) $value);
        }
        $globalParameter = GlobalParameter::updateOrCreate(['id' => $this->modelId],
            $model);

        $this->dispatch('storeElements');
        $this->showSuccessIndicator = true;

        if ($globalParameter->wasRecentlyCreated) {
            $this->modelId = $globalParameter['id'];
            $this->page = $globalParameter;
        }
    }

    public function delete(): void
    {
        $globalParameter = GlobalParameter::find($this->modelId);
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
        <x-noerd::modal-title>Globaler Parameter</x-noerd::modal-title>
    </x-slot:header>

    <livewire:language-switcher/>

    @include('noerd::components.detail.block', $pageLayout)

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false && isset($globalParameter->id)"/>
    </x-slot:footer>
</x-noerd::page>
