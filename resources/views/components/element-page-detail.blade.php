<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Traits\NoerdDetail;
use Noerd\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;
    use NoerdDetail;

    public $modelId = null; // Override trait's #[Url] - child receives ID from parent

    public array $elementLayout;
    public ElementPage $elementPage;
    public Page $page;
    public array $images = [];

    public $image;
    public $image2;

    public function mount(): void
    {
        $this->initDetail();

        $elementPage = new ElementPage;
        if ($this->modelId) {
            $elementPage = ElementPage::find($this->modelId) ?? new ElementPage;
        }

        $this->elementLayout = FieldHelper::getElementFields($elementPage->element_key) ?? [];

        $this->detailData = FieldHelper::parseElementToData($elementPage->element_key,
            json_decode($elementPage->data, true)) ?? [];
        $this->elementPage = $elementPage;

        // Send initial data to a parent component for live preview
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->modelId,
            data: $this->detailData
        );
    }

    #[Computed]
    public function elementName()
    {
        $elementFields = FieldHelper::getElementFields($this->elementPage->element_key);
        return $elementFields['title'] ?: ucwords(str_replace('_', ' ', $this->elementPage->element_key));
    }

    #[On('storeElements')]
    public function store(): void
    {
        $elementPage = ElementPage::find($this->modelId);
        if (! $elementPage) {
            return;
        }
        $elementPage->data = json_encode($this->detailData);
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
        if (! $elementPage) {
            return;
        }
        $elementPage->delete();
        $this->elementPage = new ElementPage;
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
        $token = uniqid('media_', true);
        $this->detailData['__mediaToken'] = $token;
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'media-list',
            arguments: ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $token],
        );
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if (($this->detailData['__mediaToken'] ?? null) !== $token) {
            return; // ignore events not intended for this instance
        }
        $media = Media::find($mediaId);
        if (!$media) {
            return;
        }
        data_set($this->detailData, $fieldName ?? 'image', $this->urlWithoutDomain($media));
        unset($this->detailData['__mediaToken']);

        // Notify parent to refresh live preview after media selection
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->modelId,
            data: $this->detailData
        );
    }

    public function addRepeaterItem(string $repeaterKey): void
    {
        $elementFields = FieldHelper::getElementFields($this->elementPage->element_key);
        $flatFields = FieldHelper::flattenFields($elementFields['fields'] ?? []);

        $repeaterField = collect($flatFields)->first(fn ($f) => preg_replace('/^\w+\./', '', $f['name'] ?? '') === $repeaterKey);
        if (! $repeaterField) {
            return;
        }

        $translatableTypes = ['translatableText', 'translatableRichText', 'translatableTextarea'];
        $emptyItem = [];
        foreach ($repeaterField['fields'] ?? [] as $subField) {
            if (in_array($subField['type'], $translatableTypes)) {
                $emptyItem[$subField['name']] = array_fill_keys(['de', 'en'], '');
            } else {
                $emptyItem[$subField['name']] = $subField['default'] ?? '';
            }
        }

        $this->detailData[$repeaterKey][] = $emptyItem;

        $this->dispatch('updateLiveElementData',
            elementPageId: $this->modelId,
            data: $this->detailData
        );
    }

    public function removeRepeaterItem(string $repeaterKey, int $index): void
    {
        if (isset($this->detailData[$repeaterKey][$index])) {
            $items = $this->detailData[$repeaterKey];
            array_splice($items, $index, 1);
            $this->detailData[$repeaterKey] = array_values($items);

            $this->dispatch('updateLiveElementData',
                elementPageId: $this->modelId,
                data: $this->detailData
            );
        }
    }

    public function reorderRepeaterItem(string $repeaterKey, int $fromIndex, int $toIndex): void
    {
        $items = $this->detailData[$repeaterKey] ?? [];
        if (! isset($items[$fromIndex]) || $toIndex < 0 || $toIndex >= count($items)) {
            return;
        }

        $item = array_splice($items, $fromIndex, 1)[0];
        array_splice($items, $toIndex, 0, [$item]);
        $this->detailData[$repeaterKey] = array_values($items);

        $this->dispatch('updateLiveElementData',
            elementPageId: $this->modelId,
            data: $this->detailData
        );
    }

    private function urlWithoutDomain(Media $media): string
    {
        $url = Storage::disk($media->disk)->url($media->path);

        return strstr($url, '/storage');
    }
} ?>

<div>
    @if($elementLayout)
        <div class="p-4 pt-0 border border-b-gray-200 mb-4 relative overflow-hidden rounded-lg bg-gray-950/[2.5%] after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-gray-950/5 bg-[image:radial-gradient(var(--pattern-fg)_1px,_transparent_0)] bg-[size:10px_10px] bg-fixed [--pattern-fg:var(--color-gray-950)]/5
    ">
            <x-noerd::buttons.delete
                class="!absolute !right-4 mt-4"
                wire:click="delete"
                wire:confirm="{{ __('Really delete element?') }}"
            >
            </x-noerd::buttons.delete>

            <x-noerd::tab-content :layout="$elementLayout" :model="$detailData" />
        </div>
    @else
        <div
            class="p-4 border border-red-300 mb-4 sm:p-8 relative overflow-hidden rounded-lg bg-red-50 after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-red-500/10">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <p class="text-sm font-semibold text-red-800">{{ __('Element component not found:') }} {{ $this->elementPage->element_key }}</p>
                    <p class="text-xs text-red-700 mt-2">{{ __('Please create both the .yml and .blade.php files in the elements folder.') }}</p>
                </div>
                <div>
                    <x-noerd::buttons.delete wire:confirm="{{ __('Really delete element?') }}"
                                             wire:click="delete"></x-noerd::buttons.delete>
                </div>
            </div>
            <details class="mt-2">
                <summary class="text-xs text-red-600 cursor-pointer">{{ __('Show data') }}</summary>
                <pre class="text-xs mt-2 text-red-700">{{ json_encode($this->detailData ?? [], JSON_PRETTY_PRINT) }}</pre>
            </details>
        </div>
    @endif
</div>
