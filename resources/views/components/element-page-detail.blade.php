<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\ElementPage;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdDetail;
use Noerd\Media\Models\Media;

new class extends Component {
    use WithFileUploads;
    use NoerdDetail;

    public $detailModel = ElementPage::class;

    /**
     * Nested inside the page editor (which binds ?pageId) — a dedicated alias
     * keeps the two ids apart.
     */
    public ?string $detailPrimary = 'elementId';

    public array $elementLayout;

    /**
     * The element key of the edited row — models are never stored as
     * component properties.
     */
    #[Locked]
    public ?string $elementKey = null;

    public array $images = [];

    /**
     * Correlates a media-picker round trip; kept out of $detailData so it can
     * never leak into the persisted element data.
     */
    #[Locked]
    public ?string $mediaToken = null;

    public function mount(): void
    {
        $this->initDetail();

        $elementPage = new ElementPage;
        if ($this->modelId) {
            // Tenant guard: the owning page resolves through the tenant scope,
            // so an element of another tenant's page renders as a blank one.
            $found = ElementPage::find($this->modelId);
            $elementPage = ($found && $found->page) ? $found : new ElementPage;
        }

        $this->elementKey = (string) ($elementPage->element_key ?: 'text_block_1_column');
        $this->elementLayout = FieldHelper::getElementFields($this->elementKey) ?? [];

        $elementData = is_array($elementPage->data)
            ? $elementPage->data
            : (json_decode((string) $elementPage->data, true) ?? []);
        $this->detailData = FieldHelper::parseElementToData($this->elementKey, $elementData) ?? [];

        // Send initial data to a parent component for live preview
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->modelId,
            data: $this->detailData
        );
    }

    #[Computed]
    public function elementName()
    {
        $elementFields = FieldHelper::getElementFields($this->elementKey);

        return ($elementFields['title'] ?? '') ?: ucwords(str_replace('_', ' ', (string) $this->elementKey));
    }

    #[On('storeElements')]
    public function store(): void
    {
        $elementPage = ElementPage::find($this->modelId);
        // Tenant guard: the owning page resolves through the tenant scope.
        if (! $elementPage || ! $elementPage->page) {
            return;
        }
        $elementPage->data = $this->detailData;
        $elementPage->save();
        $this->dispatch('reloadPageComponent');
    }

    public function updated($propertyName, $value): void
    {
        // When any model property changes, dispatch the live data to parent
        if (str_starts_with($propertyName, 'detailData.')) {
            $this->dispatch('updateLiveElementData',
                elementPageId: $this->modelId,
                data: $this->detailData
            );
        }
    }

    public function delete(): void
    {
        $elementPage = ElementPage::find($this->modelId);
        if (! $elementPage || ! $elementPage->page) {
            return;
        }
        $elementPage->delete();
        $this->modelId = null;
        $this->dispatch('reloadPageComponent');
    }

    public function updatedImages()
    {
        foreach ($this->images as $key => $image) {
            $link = $image->storePublicly(path: 'uploads', options: 'public');
            data_set($this->detailData, $key, '/storage/' . $link);
        }

        // Notify parent to refresh live preview with updated image paths
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->modelId,
            data: $this->detailData
        );
    }

    public function deleteImage($key)
    {
        data_set($this->detailData, $key, null);

        // Notify parent to refresh live preview after deletion
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->modelId,
            data: $this->detailData
        );
    }

    #[On('languageChanged')]
    public function languageChanged()
    {
        $this->dispatch('$refresh');
    }

    public function openSelectMediaModal(string $fieldName): void
    {
        $this->mediaToken = uniqid('media_', true);
        Noerd::modal('media::media-list', ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $this->mediaToken]);
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if ($this->mediaToken === null || $this->mediaToken !== $token) {
            return; // ignore events not intended for this instance
        }
        $media = Media::find($mediaId);
        if (!$media) {
            return;
        }
        // The id is stored, never the URL: the media disk mirrors the folder
        // tree, so a path is only true until the file is moved.
        data_set($this->detailData, $fieldName ?? 'image', $media->id);
        $this->mediaToken = null;

        // Notify parent to refresh live preview after media selection
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->modelId,
            data: $this->detailData
        );
    }

    public function dataCollectionOptions(): array
    {
        $options = ['' => __('Please select...')];

        foreach (app(CollectionDefinitionRepositoryContract::class)->all() as $definition) {
            if (! $definition->hasPage && $definition->key) {
                $options[$definition->key] = $definition->title ?: $definition->key;
            }
        }

        return $options;
    }

} ?>

<div>
    @if($elementLayout)
        <div class="p-4 pl-10 pt-0 mb-4 relative overflow-hidden rounded-lg bg-gray-950/[2.5%] after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-gray-950/5 bg-[image:radial-gradient(var(--pattern-fg)_1px,_transparent_0)] bg-[size:10px_10px] bg-fixed [--pattern-fg:var(--color-gray-950)]/5
    ">
            <div wire:sort:handle class="absolute left-2 top-4 cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 z-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
                </svg>
            </div>

            <div class="!absolute !right-4 mt-4 flex items-center gap-2">
                <button type="button"
                    wire:click="$parent.duplicateElement({{ $this->modelId }})"
                    wire:confirm="{{ __('Really copy element?') }}"
                    class="text-gray-800 hover:text-black transition"
                >
                    <x-icon name="document-duplicate" class="w-5 h-5" />
                </button>
                <button type="button"
                    wire:click="delete"
                    wire:confirm="{{ __('Really delete element?') }}"
                    class="text-red-500 hover:text-red-700 transition"
                >
                    <x-noerd::icons.trash class="w-5 h-5"/>
                </button>
            </div>

            <div x-data x-show="$store.elements?.collapsed" class="py-3 pl-2">
                <span class="text-sm font-medium text-gray-600">{{ __(($elementLayout['title'] ?? '') ?: $this->elementKey) }}</span>
            </div>

            <div x-data x-show="!$store.elements?.collapsed">
                <x-noerd::tab-content :layout="$elementLayout" :detailData="$detailData" :modelId="$modelId" />
            </div>
        </div>
    @else
        <div
            class="p-4 border border-red-300 mb-4 sm:p-8 relative overflow-hidden rounded-lg bg-red-50 after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-red-500/10">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <p class="text-sm font-semibold text-red-800">{{ __('Element component not found:') }} {{ $this->elementKey }}</p>
                    <p class="text-xs text-red-700 mt-2">{{ __('Please create both the .yml and .blade.php files in the elements folder.') }}</p>
                </div>
                <div>
                    <x-noerd::button variant="danger" wire:confirm="{{ __('Really delete element?') }}"
                                             wire:click="delete"></x-noerd::button>
                </div>
            </div>
            <details class="mt-2">
                <summary class="text-xs text-red-600 cursor-pointer">{{ __('Show data') }}</summary>
                <pre class="text-xs mt-2 text-red-700">{{ json_encode($this->detailData ?? [], JSON_PRETTY_PRINT) }}</pre>
            </details>
        </div>
    @endif
</div>
