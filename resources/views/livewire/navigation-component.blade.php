<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'navigation-component';
    public const LIST_COMPONENT = 'navigation-table';
    public const ID = 'navigationId';

    #[Url(keep: false, except: '')]
    public ?string $navigationId = null;

    public array $model = [];

    public $page;

    public function mount(Navigation $model): void
    {
        if ($this->modelId) {
            $model = Navigation::find($this->modelId);
        }
        $this->mountModalProcess(self::COMPONENT, $model);

        if ($model['page_id']) {
            $this->dispatch('pageSelected', $model['page_id']);
        }

        $this->model = FieldHelper::parseComponentToData(self::COMPONENT, $model->toArray());
    }

    public function store(): void
    {
        $this->validate([
            'model.navigation_key' => ['required', 'string', 'max:255'],
            'model.name' => ['required', 'array'],
            'model.page_id' => ['nullable', 'numeric', 'required_without:model.link'],
            'model.link' => ['nullable', 'string', 'max:2048', 'required_without:model.page_id'],
            'model.new_tab' => ['nullable', 'boolean'],
        ]);

        $model = $this->model;
        $model['tenant_id'] = auth()->user()->selected_tenant_id;
        // TODO auto detect if value is an array and convert it to JSON
        $model['name'] = json_encode($model['name']);

        if (isset($model['link'])) {
            $model['link'] = trim((string) $model['link']) ?: null;
            $model['page_id'] = null;
        }
        $model['new_tab'] = !empty($model['new_tab']) ? 1 : 0;

        $model = Navigation::updateOrCreate(['id' => $this->modelId], $model);

       $this->storeProcess($model);
    }

    public function delete(): void
    {
        if ($this->modelId) {
            $model = Navigation::find($this->modelId);
            $model?->delete();
        }
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    public function openPageSelect(): void
    {
        $this->dispatch(event: 'noerdModal', component: 'page-select-modal', source: self::COMPONENT, arguments: []);
    }

    public function openCollectionSelect(): void
    {
        $this->dispatch(event: 'noerdModal', component: 'collection-select-modal', source: self::COMPONENT, arguments: []);
    }

    #[On('pageSelected')]
    public function pageSelected($value): void
    {
        $page = Page::find($value);
        $this->model['page_id'] = $page->id;
        $decoded = is_string($page->name) ? json_decode($page->name, true) : ($page->name ?? []);
        $lang = session('selectedLanguage');
        $this->page = $decoded[$lang] ?? (is_array($decoded) ? (array_values($decoded)[0] ?? '') : $page->name);
        
        // Auto-fill name field only if it's empty
        $currentName = $this->model['name'] ?? [];
        $isNameEmpty = empty($currentName) || (is_array($currentName) && empty(array_filter($currentName)));
        
        if ($isNameEmpty) {
            $this->model['name'] = $decoded;
        }
        
        $this->collection = null;
        $this->model['link'] = null;
    }

    public function updatedModelLink($value): void
    {
        if ($value && $value !== '') {
            // Exclusivity: when link entered, clear relations
            $this->model['page_id'] = null;
            $this->page = null;
        }
    }

    #[On('languageChanged')]
    public function refresh()
    {
        $this->dispatch('$refresh');
    }

}; ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Navigation</x-noerd::modal-title>
    </x-slot:header>

    <livewire:language-switcher/>

    @include('noerd::components.detail.block', $pageLayout)

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>


