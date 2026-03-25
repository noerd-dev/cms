@if($this->pageModel?->id && $hasPageFeatures)

    <div wire:sort="elementSort">
        @foreach($this->pageModel->elements as $loopIndex => $elementPage)
            <div wire:sort:item="{{$elementPage->id}}" wire:key="sort-item-{{$elementPage->id}}">
                <div class="group/insert relative h-6 -my-3 z-10 cursor-pointer flex items-center"
                     @click="$modal('element-picker-modal', { token: 'insert-at-{{ $loopIndex }}' })">
                    <div class="flex items-center w-full opacity-0 group-hover/insert:opacity-100 transition-opacity">
                        <div class="flex-1 border-t border-dashed border-blue-400"></div>
                        <div class="mx-2 w-7 h-7 rounded-full bg-blue-500 text-white flex items-center justify-center text-lg leading-none shadow-sm">+</div>
                        <div class="flex-1 border-t border-dashed border-blue-400"></div>
                    </div>
                </div>

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
