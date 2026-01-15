<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

new class extends Component {

    use WithFileUploads;
    use Noerd;

    public const COMPONENT = 'element-page-detail';
    public const LIST_COMPONENT = 'element-pages-list';
    public const ID = 'elementPageId';

    public ?string $elementPageId = null;

    public array $elementLayout;
    public $model;
    public ElementPage $elementPage;
    public Page $page;
    public array $images = [];

    public $image;
    public $image2;

    public function mount(ElementPage $elementPage): void
    {
        if ($this->elementPageId) {
            $elementPage = ElementPage::find($this->elementPageId);
        }
        $this->elementLayout = FieldHelper::getElementFields($elementPage->element_key) ?? [];

        $this->model = FieldHelper::parseElementToData($elementPage->element_key,
            json_decode($elementPage->data, true));
        $this->elementPageId = $elementPage->id;
        $this->elementPage = $elementPage;

        // Send initial data to a parent component for live preview
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->elementPageId,
            data: $this->model
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
        $elementPage = ElementPage::find($this->elementPageId);
        $elementPage->data = json_encode($this->model);
        $elementPage->save();
        $this->dispatch('reloadPageComponent');
    }

    public function updated($propertyName, $value): void
    {
        // When any model property changes, dispatch the live data to parent
        if (str_starts_with($propertyName, 'model.')) {
            $this->dispatch('updateLiveElementData',
                elementPageId: $this->elementPageId,
                data: $this->model
            );
        }
    }

    public function delete(): void
    {
        $elementPage = ElementPage::find($this->elementPageId);
        $elementPage->delete();
        $this->dispatch('reloadPageComponent');
    }

    public function updatedImages()
    {
        foreach ($this->images as $key => $image) {
            $link = $image->storePublicly(path: 'uploads', options: 'public');
            $this->model[$key] = '/storage/' . $link;
        }

        // Notify parent to refresh live preview with updated image paths
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->elementPageId,
            data: $this->model
        );
    }

    public function deleteImage($key)
    {
        $this->model[$key] = null;

        // Notify parent to refresh live preview after deletion
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->elementPageId,
            data: $this->model
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
        $this->model['__mediaToken'] = $token;
        $this->dispatch(
            event: 'noerdModal',
            component: 'media-list',
            arguments: ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $token],
        );
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if (($this->model['__mediaToken'] ?? null) !== $token) {
            return; // ignore events not intended for this instance
        }
        $media = Media::find($mediaId);
        if (!$media) {
            return;
        }
        $this->model[$fieldName ?? 'image'] = $this->urlWithoutDomain($media);
        unset($this->model['__mediaToken']);

        // Notify parent to refresh live preview after media selection
        $this->dispatch('updateLiveElementData',
            elementPageId: $this->elementPageId,
            data: $this->model
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
        <div class="p-4 pt-0 border border-b-gray-200 mb-4 relative overflow-hidden rounded-lg bg-gray-950/[2.5%] after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-gray-950/5 dark:after:inset-ring-white/10 bg-[image:radial-gradient(var(--pattern-fg)_1px,_transparent_0)] bg-[size:10px_10px] bg-fixed [--pattern-fg:var(--color-gray-950)]/5 dark:[--pattern-fg:var(--color-white)]/10
    ">
            <x-noerd::buttons.delete
                class="!absolute !right-4 mt-4"
                wire:click="delete"
                wire:confirm="{{ __('Really delete element?') }}"
            >
            </x-noerd::buttons.delete>

            <x-noerd::tab-content :layout="$elementLayout" />
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
                <pre class="text-xs mt-2 text-red-700">{{ json_encode($this->model ?? [], JSON_PRETTY_PRINT) }}</pre>
            </details>
        </div>
    @endif
</div>
