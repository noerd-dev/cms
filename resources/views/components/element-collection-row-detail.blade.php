<?php

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\CollectionEntryStore;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Facades\Noerd;
use Noerd\Media\Models\Media;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdDetail;

    public $detailModel = Page::class;

    /**
     * Override the trait's URL-bound modelId with a dedicated alias. This editor
     * is opened as a modal nested inside the entry editor (which already binds
     * ?pageId), so a shared URL param would clobber the id on first open. The
     * dedicated ?entryId param deep-links the open row without that conflict.
     */
    public ?string $detailPrimary = 'entryId';

    public ?string $collectionKey = null;

    public ?array $collectionLayout = null;

    /**
     * Correlates a media-picker round trip; kept out of $detailData so it can
     * never leak into the persisted row data.
     */
    #[Locked]
    public ?string $mediaToken = null;

    public function mount(?string $collectionKey = null): void
    {
        $this->initDetail();

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

        $page = app(CollectionEntryStore::class)->persistRow(
            $elementCollection,
            $this->collectionLayout,
            $this->detailData,
            $this->modelId ? (int) $this->modelId : null,
        );
        $this->modelId = $page->id;

        $this->storeProcess($page);
        $this->dispatch('refreshList-collection-entries-list');
        $this->dispatch('refreshList-element-collection-field');
    }

    public function copy(): void
    {
        // copy() is not covered by the generic store/delete guard.
        if (! $this->canSaveObject()) {
            return;
        }

        $sourceRow = Page::find($this->modelId);
        if (! $sourceRow) {
            return;
        }

        $newRow = app(CollectionEntryStore::class)->duplicateRow($sourceRow);

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

    /**
     * Open the media library to pick an image for an `image` row field. The token
     * scopes the resulting event to this component instance, since several row
     * editors can be alive at once.
     */
    public function openSelectMediaModal(string $fieldName): void
    {
        $this->mediaToken = uniqid('media_', true);

        Noerd::modal('media::media-list', ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $this->mediaToken]);
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if ($this->mediaToken === null || $this->mediaToken !== $token) {
            return;
        }

        $media = Media::find($mediaId);

        if (! $media) {
            return;
        }

        // The id is stored, never the URL: the media disk mirrors the folder
        // tree, so a path is only true until the file is moved.
        $this->detailData[$fieldName ?? 'image'] = $media->id;
        $this->mediaToken = null;
    }

    public function deleteImage(string $fieldName): void
    {
        $this->detailData[$fieldName] = null;
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

}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ $collectionLayout['title'] ?? __('Entry') }}

            <div class="ml-auto">
                <livewire:cms::language-switcher/>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="$modelId" :showBlock="false">
        <x-slot:tab1>
    <div class="py-4">
        <div class="flex">
            <div class="flex ml-auto items-center mb-6 space-x-4">
                <div class="flex ml-auto items-center space-x-2">
                    <label for="sort" class="text-sm text-gray-600 font-medium">{{ __('Sort') }}:</label>
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
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        @if($modelId)
            <x-noerd::button variant="secondary" wire:click="copy" wire:confirm="{{ __('Copy this entry?') }}">
                {{ __('Copy') }}
            </x-noerd::button>
        @endif
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
