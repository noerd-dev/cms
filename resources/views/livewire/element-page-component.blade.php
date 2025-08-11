<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;
use Nywerk\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

new class extends Component {

    use WithFileUploads;
    use Noerd;

    public const COMPONENT = 'element-page-component';
    public const LIST_COMPONENT = 'element-pages-table';
    public const ID = 'elementPageId';

    public ?string $modelId = null;

    public array $elementLayout;
    public $model;
    public ElementPage $elementPage;
    public Page $page;
    public array $images = [];

    public $image;
    public $image2;

    public function mount(ElementPage $elementPage): void
    {
        if ($this->modelId) {
            $elementPage = ElementPage::find($this->modelId);
        }
        $this->elementLayout = FieldHelper::getElementFields($elementPage->element_key);

        $this->model = FieldHelper::parseElementToData($elementPage->element_key,
            json_decode($elementPage->data, true));

        $this->modelId = $elementPage->id;
        $this->elementPage = $elementPage;
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
        $elementPage->data = json_encode($this->model);
        $elementPage->save();
        $this->dispatch('reloadPageComponent');
    }

    public function delete(): void
    {
        $elementPage = ElementPage::find($this->modelId);
        $elementPage->delete();
        $this->dispatch('reloadPageComponent');
    }

    public function updatedImages()
    {
        foreach ($this->images as $key => $image) {
            $link = $image->storePublicly(path: 'uploads', options: 'public');
            $this->model[$key] = '/storage/' . $link;
        }
    }

    public function deleteImage($key)
    {
        $this->model[$key] = null;
    }

    #[On('languageChanged')]
    public function languageChanged()
    {
        $this->dispatch('$refresh');
    }

    public function openSelectMediaModal(string $fieldName): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'media-select-modal',
            arguments: ['context' => $fieldName],
        );
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image'): void
    {
        $media = Media::find($mediaId);
        if (!$media) {
            return;
        }
        $this->model[$fieldName ?? 'image'] = Storage::disk($media->disk)->url($media->path);
    }
} ?>

<div>
    <div class="p-4 border border-b-gray-200 mb-4 sm:p-8 relative overflow-hidden rounded-lg bg-gray-950/[2.5%] after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-gray-950/5 dark:after:inset-ring-white/10 bg-[image:radial-gradient(var(--pattern-fg)_1px,_transparent_0)] bg-[size:10px_10px] bg-fixed [--pattern-fg:var(--color-gray-950)]/5 dark:[--pattern-fg:var(--color-white)]/10
    ">
        <div class="text-sm">{{$this->elementName()}}  </div>

        <x-noerd::buttons.delete
            class="!absolute !right-4"
            wire:click="delete"
            wire:confirm="Element wirklich löschen?"
        >
        </x-noerd::buttons.delete>

        @include('noerd::components.detail.block', $elementLayout)
    </div>
</div>
