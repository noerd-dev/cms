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
     * Override the trait's URL-bound modelId. This editor is opened as a modal
     * nested inside the entry editor (which already binds ?pageId), so a shared
     * URL param would clobber the id on first open. No #[Url] here.
     */
    public $modelId = null;

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
        $this->dispatch('closeTopModal');
    }

    public function delete(): void
    {
        if ($this->modelId) {
            Page::find($this->modelId)?->delete();
        }

        $this->dispatch('closeTopModal');
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

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ $collectionLayout['title'] ?? __('Eintrag') }}

            <div class="ml-auto">
                <livewire:cms::language-switcher/>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <div class="py-4">
        @include('noerd::components.detail.block', array_merge($collectionLayout ?? ['fields' => []], ['model' => $detailData, 'modelId' => $modelId]))
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="(bool) $modelId"/>
    </x-slot:footer>
</x-noerd::page>
