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
use Noerd\Noerd\Traits\NoerdModelTrait;

new class extends Component {

    use NoerdModelTrait;

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
        return Element::where('tenant_id', auth()->user()->selected_tenant_id)->get();
    }

    public function mount(Page $page): void
    {
        $this->pageLayout = StaticConfigHelper::getComponentFields('page');
        if ($this->modelId) {
            $page = Page::find($this->modelId);
        }

        $this->model = $page->toArray();
        $this->model = FieldHelper::parseComponentToData('page', $page->toArray());

        $this->pageId = $page->id;
        $this->page = $page;
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

    public function addElement($elementId)
    {
        $sortElement = ElementPage::where('page_id', $this->modelId)
            ->orderBy('sort', 'desc')
            ->first();
        $element = Element::find($elementId);
        $this->page->elements()->attach($element->id, [
            'sort' => $sortElement?->sort ?? 0 + 1,
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
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>Seite</x-noerd::modal-title>
    </x-slot:header>

    <livewire:language-switcher/>

    @include('noerd::components.detail.block', $pageLayout)

    @if($this->page->id)
        <div x-noerd::sort="$wire.elementSort($item, $position)">
            @foreach($this->page->elements as $element)
                <div x-noerd::sort:item="{{$element->pivot->id}}">
                    <livewire:element-page-component
                        wire:key="{{$element->pivot->id . $lastChangeTime}}"
                        :modelId="$element->pivot->id"
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
                        <div wire:click="addElement({{$element->id}})"
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
