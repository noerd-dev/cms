<?php

use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\SafeUrl;
use Noerd\Facades\Noerd;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'navigationId';

    public $detailModel = Navigation::class;

    public array $relations = [];

    public function mount(): void
    {
        $this->initDetail();

        $navigation = new Navigation();
        if ($this->modelId) {
            $navigation = Navigation::find($this->modelId) ?? new Navigation();
        }

        $this->detailData = FieldHelper::parseComponentToData($this->componentName(), $navigation->toArray());

        if (! $this->modelId && ! empty($this->relations['parent_id'])) {
            $this->detailData['parent_id'] = $this->relations['parent_id'];
        }

        if ($navigation['page_id']) {
            $this->pageSelected($navigation['page_id'], 'detailData.page_id');
        }
    }

    /**
     * Page collections of the tenant — the options of the YAML select
     * (`optionsMethod: collectionOptions`).
     *
     * @return array<int, string>
     */
    public function collectionOptions(): array
    {
        $options = [];

        foreach (Collection::query()->orderBy('name')->get() as $collection) {
            $config = CollectionHelper::getCollectionFields(mb_strtolower((string) $collection->collection_key));
            if ($config && ! empty($config['hasPage'])) {
                $options[$collection->id] = (string) $collection->name;
            }
        }

        return $options;
    }

    public function parentOptions(): array
    {
        $tenantId = TenantHelper::currentTenantId();
        $query = Navigation::where('tenant_id', $tenantId)
            ->whereNull('parent_id');

        if ($this->modelId) {
            $query->where('id', '!=', $this->modelId);
        }

        $options = ['' => '-- ' . __('No parent item') . ' --'];

        foreach ($query->orderBy('sort_order')->get() as $item) {
            $label = RelationFieldDefinition::normalizeDisplayValue($item->name);
            $options[$item->id] = $label ?: '(ID: ' . $item->id . ')';
        }

        return $options;
    }

    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        $tenantId = TenantHelper::currentTenantId();
        $parentId = ! empty($this->detailData['parent_id']) ? $this->detailData['parent_id'] : null;
        $this->detailData['parent_id'] = $parentId;
        $isSub = $parentId !== null;

        // Parent, page and collection must be records of the current tenant —
        // the ids arrive from the client.
        $this->validate([
            'detailData.navigation_key' => [$isSub ? 'nullable' : 'required', 'string', 'max:255'],
            'detailData.name' => ['required', 'array'],
            'detailData.parent_id' => ['nullable', 'integer', Rule::exists('cms_navigations', 'id')->where('tenant_id', $tenantId)],
            'detailData.sort_order' => ['nullable', 'integer', 'min:0'],
            'detailData.page_id' => ['nullable', 'integer', Rule::exists('cms_pages', 'id')->where('tenant_id', $tenantId)],
            // The column holds 255 characters; a link is printed into the
            // public navigation, so only safe URLs (http(s), mailto, tel,
            // site paths) are accepted — never javascript: or //other-host.
            'detailData.link' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && mb_trim($value) !== '' && ! SafeUrl::isSafe($value)) {
                    $fail(__('The link must be a web address (http/https), an e-mail or phone link, or a path on this website.'));
                }
            }],
            'detailData.collection_id' => ['nullable', 'integer', Rule::exists('cms_collections', 'id')->where('tenant_id', $tenantId)],
            'detailData.new_tab' => ['nullable', 'boolean'],
        ]);

        $parentId = $parentId !== null ? (int) $parentId : null;

        if ($parentId !== null && $this->wouldCreateCycle($parentId)) {
            $this->addError('detailData.parent_id', __('An item cannot be placed below itself or one of its sub items.'));

            return;
        }

        $data = $this->writableDetailData(Navigation::class);

        if ($isSub) {
            $parent = Navigation::find($parentId);
            $data['navigation_key'] = $parent?->navigation_key ?? $data['navigation_key'] ?? null;
        }

        $data['collection_id'] = ! empty($data['collection_id']) ? (int) $data['collection_id'] : null;

        if (! empty(mb_trim((string) ($data['link'] ?? '')))) {
            $data['link'] = mb_trim((string) $data['link']);
            $data['page_id'] = null;
        } else {
            $data['link'] = null;
            $data['page_id'] = ! empty($data['page_id']) ? (int) $data['page_id'] : null;
        }
        $data['new_tab'] = ! empty($data['new_tab']) ? 1 : 0;
        $data['parent_id'] = $parentId;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($this->modelId) {
            $navigation = Navigation::find($this->modelId);

            // An id the tenant scope cannot resolve stores nothing — never a new item.
            if (! $navigation instanceof Navigation) {
                return;
            }

            $navigation->fill($data)->save();
        } else {
            $navigation = Navigation::create($data);
        }

        $this->finishStore($navigation);
    }

    public function openPageSelect(): void
    {
        Noerd::modal('cms::pages-list', ['listActionMethod' => 'selectAction', 'context' => 'detailData.page_id']);
    }

    #[On('pageSelected')]
    public function pageSelected($value, mixed $context = 'detailData.page_id'): void
    {
        if ($context !== 'detailData.page_id') {
            return;
        }

        $page = Page::find($value);
        if (! $page) {
            return;
        }

        $this->detailData['page_id'] = $page->id;
        $decoded = is_array($page->name)
            ? $page->name
            : (json_decode((string) $page->name, true) ?: []);
        $this->relationTitles['page_id'] = RelationFieldDefinition::normalizeDisplayValue($page->name);

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

    /**
     * Whether placing this item below $parentId would make it its own
     * ancestor — the parent itself, or any item up the parent's chain.
     */
    private function wouldCreateCycle(int $parentId): bool
    {
        if (! $this->modelId) {
            return false;
        }

        $seen = [];
        $currentId = $parentId;

        while ($currentId !== null && ! isset($seen[$currentId])) {
            if ($currentId === (int) $this->modelId) {
                return true;
            }

            $seen[$currentId] = true;
            $parent = Navigation::query()->whereKey($currentId)->value('parent_id');
            $currentId = $parent !== null ? (int) $parent : null;
        }

        return $currentId !== null;
    }

    #[On('languageChanged')]
    public function onLanguageChanged(): void
    {
        // The roundtrip re-renders the translatable inputs against the new language.
    }

}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ __('Navigation Point') }}

            <div class="ml-auto">
                <livewire:cms::language-switcher/>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>


