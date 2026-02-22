@if($this->pageModel?->id && $hasPageFeatures)

    <div x-sort="$wire.elementSort($item, $position)">
        @foreach($this->pageModel->elements as $loopIndex => $elementPage)
            <div x-sort:item="{{$elementPage->id}}" wire:key="sort-item-{{$elementPage->id}}">
                <livewire:element-page-detail
                    wire:key="element-page-{{$elementPage->id}}"
                    :modelId="$elementPage->id"
                >
                </livewire:element-page-detail>
            </div>
        @endforeach
    </div>

    <div class="mt-8 mb-8 flex">
        <div class="mt-4 mx-auto">
            <x-noerd::buttons.primary
                @click="$modal('element-picker-modal', { token: 'insert-end' })">
                {{ __('Add Element') }}
            </x-noerd::buttons.primary>
        </div>
    </div>

@endif
