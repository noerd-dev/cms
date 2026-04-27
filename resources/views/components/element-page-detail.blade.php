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
            modalComponent: 'media::media-list',
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

    public function dataCollectionOptions(): array
    {
        $collectionsPath = base_path('app-configs/cms/collections');
        $options = ['' => __('Please select...')];

        foreach (glob($collectionsPath . '/*.yml') as $file) {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($file);
            if (($config['hasPage'] ?? true) === false) {
                $key = $config['key'] ?? '';
                $title = $config['title'] ?? $key;
                if ($key) {
                    $options[$key] = $title;
                }
            }
        }

        return $options;
    }

    private function urlWithoutDomain(Media $media): string
    {
        $url = Storage::disk($media->disk)->url($media->path);

        return strstr($url, '/storage');
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
                    <svg class="w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M15.9877 3.0124C17.1186 3.1311 18.0001 4.08761 18.0001 5.25V11.75C18.0001 12.9926 16.9927 14 15.7501 14H13.5001V10.6213C13.5001 9.82567 13.184 9.06261 12.6214 8.5L9.50008 5.37868C9.1106 4.9892 8.62505 4.71787 8.09912 4.58776C8.35944 3.74123 9.10578 3.10756 10.0125 3.0124C10.1312 1.88145 11.0877 1 12.2501 1H13.7501C14.9125 1 15.869 1.88145 15.9877 3.0124ZM11.5001 3.25C11.5001 2.83579 11.8359 2.5 12.2501 2.5H13.7501C14.1643 2.5 14.5001 2.83579 14.5001 3.25V3.5H11.5001V3.25Z"/><path d="M3.5 6C2.67157 6 2 6.67157 2 7.5V16.5C2 17.3284 2.67157 18 3.5 18H10.5C11.3284 18 12 17.3284 12 16.5V10.6213C12 10.2235 11.842 9.84196 11.5607 9.56066L8.43934 6.43934C8.15804 6.15804 7.7765 6 7.37868 6H3.5Z"/></svg>
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
                <span class="text-sm font-medium text-gray-600">{{ __($elementLayout['title'] ?? $this->elementPage->element_key) }}</span>
            </div>

            <div x-data x-show="!$store.elements?.collapsed">
                <x-noerd::tab-content :layout="$elementLayout" :model="$detailData" />
            </div>
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
