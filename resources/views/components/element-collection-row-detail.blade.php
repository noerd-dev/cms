<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Helpers\TenantHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;

    /**
     * Override the trait's URL-bound modelId with a dedicated alias. This editor
     * is opened as a modal nested inside the entry editor (which already binds
     * ?pageId), so a shared URL param would clobber the id on first open. The
     * dedicated ?entry param deep-links the open row without that conflict.
     */
    public ?string $detailPrimary = 'entry';

    public ?string $collectionKey = null;

    public ?array $collectionLayout = null;

    public function mount(?string $collectionKey = null): void
    {
        $this->collectionKey = $collectionKey ?? $this->collectionKey;
        $this->collectionLayout = CollectionHelper::getCollectionFields($this->collectionKey);

        $row = $this->modelId ? Page::find($this->modelId) : null;
        $data = is_array($row?->data) ? $row->data : [];

        $this->detailData = $this->initializeDetailData($data);
        $this->detailData['sort'] = $row->sort ?? 0;
    }

    #[On('languageChanged')]
    public function onLanguageChanged(): void
    {
        // The roundtrip re-renders the translatable inputs against the new language.
    }

    public function store(): void
    {
        $elementCollection = $this->elementCollection();
        if (! $elementCollection) {
            return;
        }

        $fieldData = [];
        foreach ($this->collectionLayout['fields'] ?? [] as $field) {
            $key = str_replace('detailData.', '', $field['name'] ?? '');
            if ($key === '') {
                continue;
            }
            $fieldData[$key] = $this->detailData[$key] ?? null;
        }

        $attributes = [
            'tenant_id' => $elementCollection->tenant_id,
            'collection_id' => $elementCollection->id,
            'data' => $fieldData,
            'is_active' => true,
        ];

        if ($this->modelId) {
            $attributes['sort'] = (int) ($this->detailData['sort'] ?? 0);
            $page = Page::updateOrCreate(['id' => $this->modelId], $attributes);
        } else {
            // Append new rows after the existing ones.
            $attributes['sort'] = (int) ($elementCollection->rows()->max('sort') ?? -1) + 1;
            $page = Page::create($attributes);
            $this->modelId = $page->id;
        }

        $this->storeProcess($page);
        $this->dispatch('refreshList-collection-entries-list');
        $this->dispatch('refreshList-element-collection-field');
    }

    public function copy(): void
    {
        $sourceRow = Page::find($this->modelId);
        if (! $sourceRow) {
            return;
        }

        Page::where('collection_id', $sourceRow->collection_id)
            ->where('sort', '>', $sourceRow->sort ?? 0)
            ->increment('sort');

        $newRow = $sourceRow->replicate(['id']);
        $newRow->sort = ($sourceRow->sort ?? 0) + 1;
        $newRow->save();

        $this->modelId = $newRow->id;
        $this->detailData['sort'] = $newRow->sort;

        $this->dispatch('refreshList-collection-entries-list');
        $this->dispatch('refreshList-element-collection-field');
        $this->showSuccessIndicator = true;
    }

    public function delete(): void
    {
        if ($this->modelId) {
            Page::find($this->modelId)?->delete();
        }

        $this->dispatch('refreshList-element-collection-field');
        $this->closeModalProcess('collection-entries-list');
    }

    private function elementCollection(): ?Collection
    {
        return Collection::query()
            ->where('collection_key', mb_strtoupper((string) $this->collectionKey))
            ->where('is_element_collection', true)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function initializeDetailData(array $data): array
    {
        $languages = $this->activeLanguageCodes();

        foreach ($this->collectionLayout['fields'] ?? [] as $field) {
            $key = str_replace('detailData.', '', $field['name'] ?? '');
            if ($key === '') {
                continue;
            }

            if (str_starts_with($field['type'] ?? '', 'translatable')) {
                $existing = is_array($data[$key] ?? null) ? $data[$key] : [];
                foreach ($languages as $language) {
                    $existing[$language] = is_string($existing[$language] ?? null) ? $existing[$language] : '';
                }
                $data[$key] = $existing;
            } else {
                $data[$key] ??= '';
            }
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function activeLanguageCodes(): array
    {
        $tenantId = auth()->user()?->selected_tenant_id ?? TenantHelper::getSelectedTenantId();

        $codes = CmsLanguage::where('tenant_id', $tenantId)->pluck('code')->all();

        return $codes ?: ['de'];
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ $collectionLayout['title'] ?? __('Eintrag') }}

            <div class="ml-auto">
                <livewire:cms::language-switcher/>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <div class="py-4">
        <div class="flex">
            <div class="flex ml-auto items-center mb-6 space-x-4">
                <div class="flex ml-auto items-center space-x-2">
                    <label for="sort" class="text-sm text-gray-600 font-medium">Sort:</label>
                    <input
                        wire:model="detailData.sort"
                        id="sort"
                        type="number"
                        min="0"
                        step="1"
                        class="w-16 rounded-md border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                    />
                </div>
            </div>
        </div>

        @include('noerd::components.detail.block', array_merge($collectionLayout ?? ['fields' => []], ['model' => $detailData, 'modelId' => $modelId]))
    </div>

    <x-slot:footer>
        @if($modelId)
            <x-noerd::button variant="secondary" wire:click="copy" wire:confirm="{{ __('Eintrag kopieren?') }}">
                {{ __('Copy') }}
            </x-noerd::button>
        @endif
        <x-noerd::delete-save-bar :showDelete="(bool) $modelId"/>
    </x-slot:footer>
</x-noerd::page>
