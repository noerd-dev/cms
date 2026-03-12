<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    #[Url(as: 'navigationId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = Navigation::class;

    public array $navigationData = [];

    public function mount(): void
    {
        $this->initDetail();

        $navigation = new Navigation;
        if ($this->modelId) {
            $navigation = Navigation::find($this->modelId) ?? new Navigation;
        }

        if ($navigation['page_id']) {
            $this->dispatch('pageSelected', $navigation['page_id']);
        }

        $this->navigationData = FieldHelper::parseComponentToData($this->getComponentName(), $navigation->toArray());
    }

    public function store(): void
    {
        $this->validate([
            'navigationData.navigation_key' => ['required', 'string', 'max:255'],
            'navigationData.name' => ['required', 'array'],
            'navigationData.page_id' => ['nullable', 'numeric', 'required_without:navigationData.link'],
            'navigationData.link' => ['nullable', 'string', 'max:2048', 'required_without:navigationData.page_id'],
            'navigationData.new_tab' => ['nullable', 'boolean'],
        ]);

        $data = $this->navigationData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;
        // TODO auto detect if value is an array and convert it to JSON
        $data['name'] = json_encode($data['name']);

        if (isset($data['link'])) {
            $data['link'] = trim((string) $data['link']) ?: null;
            $data['page_id'] = null;
        }
        $data['new_tab'] = !empty($data['new_tab']) ? 1 : 0;

        $navigation = Navigation::updateOrCreate(['id' => $this->modelId], $data);

        $this->storeProcess($navigation);
    }

    public function delete(): void
    {
        if ($this->modelId) {
            $navigation = Navigation::find($this->modelId);
            $navigation?->delete();
        }
        $this->closeModalProcess($this->getListComponent());
    }

    public function openPageSelect(): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'pages-list',
            source: $this->getComponentName(),
            arguments: ['listActionMethod' => 'selectAction'],
        );
    }

    #[On('pageSelected')]
    public function pageSelected($value): void
    {
        $page = Page::find($value);
        $this->navigationData['page_id'] = $page->id;
        $decoded = is_string($page->name) ? json_decode($page->name, true) : ($page->name ?? []);
        $lang = session('selectedLanguage');
        $this->relationTitles['page_id'] = $decoded[$lang] ?? (is_array($decoded) ? (array_values($decoded)[0] ?? '') : $page->name);

        // Auto-fill name field only if it's empty
        $currentName = $this->navigationData['name'] ?? [];
        $isNameEmpty = empty($currentName) || (is_array($currentName) && empty(array_filter($currentName)));

        if ($isNameEmpty) {
            $this->navigationData['name'] = $decoded;
        }

        $this->navigationData['link'] = null;
    }

    public function updatedNavigationDataLink($value): void
    {
        if ($value && $value !== '') {
            // Exclusivity: when link entered, clear relations
            $this->navigationData['page_id'] = null;
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
        <x-noerd::modal-title class="flex items-center">
            {{ __('cms_navigation_point') }}

            <div class="ml-auto" :class="isModal ? 'mr-22' : ''">
                <div class="flex bg-white p-1 rounded-lg w-fit border border-gray-200">
                    <livewire:language-switcher/>
                </div>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>


