<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Element;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'page-component';
    public const LIST_COMPONENT = 'pages-table';
    public const ID = 'pageId';
    #[Url(keep: false, except: '')]
    public $pageId = null;

    public array $model;
    public Page $page;

    #[Computed]
    public function elements()
    {
        return FieldHelper::getAllElements();
    }

    public function mount(Page $model): void
    {
        if ($this->modelId) {
            $model = Page::find($this->modelId);
        }

        $this->page = $model;
        $this->mountModalProcess(self::COMPONENT, $model);

        // Special case for Page/Element fields
        $this->model = FieldHelper::parseComponentToData('page-component', $model->toArray());

        $this->lastChangeTime = time();
    }

    public function store(): void
    {
        $this->validate([
            'model.name' => ['required', 'array', 'max:255'],
        ]);

        $model = $this->model;
        $model['tenant_id'] = auth()->user()->selected_tenant_id;
        // TODO auto detect if value is an array and convert it to JSON
        $model['slug'] = json_encode($this->model['slug']);
        $model['name'] = json_encode($this->model['name']);
        $page = Page::updateOrCreate(['id' => $this->modelId],
            $model);

        $this->dispatch('storeElements');
        $this->showSuccessIndicator = true;

        if ($page->wasRecentlyCreated) {
            $this->modelId = $page['id'];
            $this->page = $page;
        }
    }

    public function delete(): void
    {
        $page = Page::find($this->modelId);
        $page->delete();
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    public function addElement($elementKey)
    {
        $sortElement = ElementPage::where('page_id', $this->modelId)
            ->orderBy('sort', 'desc')
            ->first();

        ElementPage::create([
            'page_id' => $this->modelId,
            'element_key' => $elementKey,
            'sort' => ($sortElement?->sort ?? 0) + 1,
            'data' => '{}',
        ]);

        $this->lastChangeTime = time();
    }

    public function elementSort($elementId, $newPosition): void
    {
        $elements = ElementPage::where('page_id', $this->modelId)
            ->orderBy('sort')
            ->get();
        $loop = 0;
        foreach ($elements as $element) {
            if ($newPosition === $loop) {
                $loop++;
            }
            if ($element['id'] === $elementId) {
                $element->sort = $newPosition;
                $element->save();
            } else {
                $element->sort = $loop++;
                $element->save();
            }
        }
        $this->lastChangeTime = time();
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

    public function openElements()
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'element-page-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $this->pageId],
        );
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Seite</x-noerd::modal-title>
    </x-slot:header>

    <livewire:language-switcher/>

    @include('noerd::components.detail.block', $pageLayout)

    @if($this->page->id)

        <button wire:click="openElements">
            Seitenelemente bearbeiten
        </button>

        <div x-sort="$wire.elementSort($item, $position)">
            @foreach($this->page->elements as $elementPage)
                <div x-sort:item="{{$elementPage->id}}">
                    <livewire:element-page-component
                        wire:key="{{$elementPage->id . $lastChangeTime}}"
                        :modelId="$elementPage->id"
                    >
                    </livewire:element-page-component>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            <x-noerd::title>Element hinzufügen</x-noerd::title>
            <div class="mt-4">
                <div class="grid grid-cols-3 gap-8">
                    @foreach($this->elements() as $element)
                        <div wire:click="addElement('{{$element->element_key}}')"
                             class="text-sm hover:bg-gray-200 bg-gray-100 cursor-pointer border-dotted border p-4 text-center">
                            <div class="font-bold"> {{$element->name}} </div>
                            {{$element->description}}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false && isset($page->id)"/>
    </x-slot:footer>
</x-noerd::page>
