<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'navigationId';

    public $detailModel = Navigation::class;

    public array $relations = [];

    public function mount(): void
    {
        $this->initDetail();
        $this->injectCollectionOptions();

        $navigation = new Navigation;
        if ($this->modelId) {
            $navigation = Navigation::find($this->modelId) ?? new Navigation;
        }

        $this->detailData = FieldHelper::parseComponentToData($this->getComponentName(), $navigation->toArray());

        if (! $this->modelId && ! empty($this->relations['parent_id'])) {
            $this->detailData['parent_id'] = $this->relations['parent_id'];
        }

        if ($navigation['page_id']) {
            $this->pageSelected($navigation['page_id']);
        }
    }

    private function injectCollectionOptions(): void
    {
        $tenantId = auth()->user()->selected_tenant_id;
        $collections = Collection::where('tenant_id', $tenantId)->orderBy('name')->get();

        $options = [['value' => '', 'label' => '-- ' . __('Please select...') . ' --']];
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
        $parentId = ! empty($this->detailData['parent_id']) ? (int) $this->detailData['parent_id'] : null;
        $this->detailData['parent_id'] = $parentId;
        $isSub = $parentId !== null;

        $this->validate([
            'detailData.navigation_key' => [$isSub ? 'nullable' : 'required', 'string', 'max:255'],
            'detailData.name' => ['required', 'array'],
            'detailData.parent_id' => ['nullable', 'numeric', 'exists:cms_navigations,id'],
            'detailData.sort_order' => ['nullable', 'integer', 'min:0'],
            'detailData.page_id' => ['nullable', 'numeric'],
            'detailData.link' => ['nullable', 'string', 'max:2048'],
            'detailData.collection_id' => ['nullable', 'numeric', 'exists:collections,id'],
            'detailData.new_tab' => ['nullable', 'boolean'],
        ]);

        $data = $this->detailData;
        unset($data['navigation_type']);
        $data['tenant_id'] = auth()->user()->selected_tenant_id;

        if ($isSub) {
            $parent = Navigation::find($parentId);
            $data['navigation_key'] = $parent?->navigation_key ?? $data['navigation_key'];
        }
        // TODO auto detect if value is an array and convert it to JSON
        $data['name'] = json_encode($data['name']);

        $data['collection_id'] = !empty($data['collection_id']) ? (int) $data['collection_id'] : null;

        if (!empty(trim((string) ($data['link'] ?? '')))) {
            $data['link'] = trim((string) $data['link']);
            $data['page_id'] = null;
        } else {
            $data['link'] = null;
            $data['page_id'] = !empty($data['page_id']) ? (int) $data['page_id'] : null;
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
        Noerd::modal('cms::pages-list', ['listActionMethod' => 'selectAction']);
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

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ __('Navigation Point') }}

            <div class="ml-auto">
                <div class="flex bg-white p-1 rounded-lg w-fit border border-gray-200">
                    <livewire:cms::language-switcher/>
                </div>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>


