<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    #[Url(as: 'navigationId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = Navigation::class;

    public function mount(): void
    {
        $this->initDetail();
        $this->injectCollectionOptions();

        $navigation = new Navigation;
        if ($this->modelId) {
            $navigation = Navigation::find($this->modelId) ?? new Navigation;
        }

        $this->detailData = FieldHelper::parseComponentToData($this->getComponentName(), $navigation->toArray());

        if ($navigation['page_id']) {
            $this->pageSelected($navigation['page_id']);
        }
    }

    private function injectCollectionOptions(): void
    {
        $tenantId = auth()->user()->selected_tenant_id;
        $collections = Collection::where('tenant_id', $tenantId)->orderBy('name')->get();

        $options = [['value' => '', 'label' => '-- ' . __('noerd_please_select') . ' --']];
        foreach ($collections as $collection) {
            $config = CollectionHelper::getCollectionFields(strtolower($collection->collection_key));
            if ($config && !empty($config['hasPage'])) {
                $options[] = ['value' => $collection->id, 'label' => $collection->name];
            }
        }

        foreach ($this->pageLayout['fields'] as &$field) {
            if (($field['name'] ?? '') === 'detailData.collection_id') {
                $field['options'] = $options;
                break;
            }
        }
    }

    public function parentOptions(): array
    {
        $tenantId = auth()->user()->selected_tenant_id;
        $query = Navigation::where('tenant_id', $tenantId)
            ->whereNull('parent_id');

        if ($this->modelId) {
            $query->where('id', '!=', $this->modelId);
        }

        if (!empty($this->detailData['navigation_key'])) {
            $query->where('navigation_key', $this->detailData['navigation_key']);
        }

        $selectedLanguage = session('selectedLanguage', 'de');
        $options = ['' => '-- Kein übergeordneter Punkt --'];

        foreach ($query->orderBy('sort_order')->get() as $item) {
            $decoded = is_string($item->name) ? json_decode($item->name, true) : ($item->name ?? []);
            $label = $decoded[$selectedLanguage] ?? (is_array($decoded) ? (array_values($decoded)[0] ?? '') : $item->name);
            $options[$item->id] = $label ?: '(ID: ' . $item->id . ')';
        }

        return $options;
    }

    public function store(): void
    {
        $hasCollection = !empty($this->detailData['collection_id']);

        $this->validate([
            'detailData.navigation_key' => ['required', 'string', 'max:255'],
            'detailData.name' => ['required', 'array'],
            'detailData.parent_id' => ['nullable', 'numeric', 'exists:cms_navigations,id'],
            'detailData.sort_order' => ['nullable', 'integer', 'min:0'],
            'detailData.page_id' => ['nullable', 'numeric', $hasCollection ? '' : 'required_without:detailData.link'],
            'detailData.link' => ['nullable', 'string', 'max:2048', $hasCollection ? '' : 'required_without:detailData.page_id'],
            'detailData.collection_id' => ['nullable', 'numeric', 'exists:collections,id'],
            'detailData.new_tab' => ['nullable', 'boolean'],
        ]);

        $data = $this->detailData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;
        // TODO auto detect if value is an array and convert it to JSON
        $data['name'] = json_encode($data['name']);

        $data['collection_id'] = !empty($data['collection_id']) ? (int) $data['collection_id'] : null;

        if ($data['collection_id']) {
            $data['page_id'] = null;
            $data['link'] = null;
        } elseif (isset($data['link'])) {
            $data['link'] = trim((string) $data['link']) ?: null;
            $data['page_id'] = null;
        }
        $data['new_tab'] = !empty($data['new_tab']) ? 1 : 0;
        $data['parent_id'] = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

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
        $this->detailData['page_id'] = $page->id;
        $decoded = is_string($page->name) ? json_decode($page->name, true) : ($page->name ?? []);
        $lang = session('selectedLanguage');
        $this->relationTitles['page_id'] = $decoded[$lang] ?? (is_array($decoded) ? (array_values($decoded)[0] ?? '') : $page->name);

        // Auto-fill name field only if it's empty
        $currentName = $this->detailData['name'] ?? [];
        $isNameEmpty = empty($currentName) || (is_array($currentName) && empty(array_filter($currentName)));

        if ($isNameEmpty) {
            $this->detailData['name'] = $decoded;
        }

        $this->detailData['link'] = null;
    }

    public function updatedDetailDataLink($value): void
    {
        if ($value && $value !== '') {
            // Exclusivity: when link entered, clear relations
            $this->detailData['page_id'] = null;
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


