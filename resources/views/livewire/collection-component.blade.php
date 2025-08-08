<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;
    use WithFileUploads;

    public const COMPONENT = 'collection-component';
    public const LIST_COMPONENT = 'collections-table';
    public const ID = 'collectionId';
    #[Url(keep: false, except: '')]
    public ?string $collectionId = null;

    public array $model;
    public Collection $collectionModel;

    public int $sort = 0;

    public array $images = [];

    #[Url]
    public ?string $key = null;

    public function mount(Collection $collection): void
    {
        if ($this->modelId) {
            $collection = Collection::find($this->modelId);
            $this->model = json_decode($collection->data, true);
        }

        $this->pageLayout = CollectionHelper::getCollectionFields($this->key);
        $this->modelId = $collection->id;
        $this->sort = $collection->sort ?? 0;
        $this->collectionId = $collection->id;
        $this->collectionModel = $collection;
    }

    public function store(): void
    {
        $this->model['tenant_id'] = auth()->user()->selected_tenant_id;

        $collection = Collection::updateOrCreate(['id' => $this->modelId], [
            'tenant_id' => auth()->user()->selected_tenant_id,
            'collection_key' => strtoupper($this->key),
            'data' => json_encode($this->model),
        ]);

        // Only create a page if hasPage is set to true in the collection yml file
        if (!$collection->page_id && ($this->pageLayout['hasPage'] ?? false)) {
            $page = new Page();
            // TOOD refactor
            $page->name = '{"de":"CollectionPage","en":"CollectionPage"}';
            $page->tenant_id = auth()->user()->selected_tenant_id;
            $page->save();
            $collection->page_id = $page->id;
        }

        $collection->sort = $this->sort ?? 0;
        $collection->save();

        $this->showSuccessIndicator = true;

        if ($collection->wasRecentlyCreated) {
            $this->modelId = $collection['id'];
            $this->collectionModel = $collection;
        }
    }

    public function delete(): void
    {
        $collection = Collection::find($this->modelId);
        $collection->delete();
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    // TODO: Slider brauchen in einer Colleciton noch Bilder
    // Dies aber ähnlich, wie in element-page-component lösen
    public function updatedImages(): void
    {
        foreach ($this->images as $key => $image) {
            $link = $image->storePublicly(path: 'uploads', options: 'public');
            $this->model[$key] = '/storage/' . $link;
        }
    }

    public function deleteImage(string $fieldName): void
    {
        $this->model[$fieldName] = null;
    }

    #[On('reloadPageComponent')]
    public function reloadPage()
    {
        $this->lastChangeTime = time();
    }

    #[On('languageChanged')]
    public function refresh()
    {
        $this->dispatch('$refresh');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{$pageLayout['title']}}</x-noerd::modal-title>
    </x-slot:header>

    <livewire:language-switcher/>

    <div class="flex w-full">
        <div class="flex ml-auto">
            <label class="pt-2.5 text-sm">Sortierung</label>
            <x-noerd::text-input
                label="Sortierung"
                type="number"
                name="sort"
                class="!w-20 ml-2"
                wire:model="sort"></x-noerd::text-input>
        </div>
    </div>

    @include('noerd::components.detail.block', $pageLayout)

    @if($modelId && ($pageLayout['hasPage'] ?? false) && $collectionModel->page_id)
        <x-noerd::primary-button
            wire:click="$dispatch('noerdModal', {component: 'page-component', arguments: {modelId: {{$collectionModel->page_id}} }})">
            Seite bearbeiten
        </x-noerd::primary-button>
    @endif

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$modelId"/>
    </x-slot:footer>
</x-noerd::page>
