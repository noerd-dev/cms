<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\NoerdModelTrait;

new class extends Component {

    use NoerdModelTrait;

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
    }

    public function store(): void
    {
        $this->validate([
            'model.key' => ['required', 'string', 'max:255'],
            'model.value' => ['required'],
        ]);

        $model = $this->model;
        $model['tenant_id'] = auth()->user()->selected_tenant_id;
        // TODO auto detect if value is an array and convert it to JSON
        $model['value'] = json_encode($this->model['value']);
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
