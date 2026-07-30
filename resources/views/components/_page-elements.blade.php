@if ($this->pageModel?->id && $hasPageFeatures)
    <div wire:sort="elementSort">
        @foreach ($this->pageModel->elements as $loopIndex => $elementPage)
            <div wire:sort:item="{{ $elementPage->id }}" wire:key="sort-item-{{ $elementPage->id }}">
                <div
                    class="group/insert relative z-10 -my-3 flex h-6 cursor-pointer items-center"
                    @click="$modal('cms::element-picker-modal', { token: 'insert-at-{{ $loopIndex }}' })"
                >
                    <div class="flex w-full items-center opacity-0 transition-opacity group-hover/insert:opacity-100">
                        <div class="flex-1 border-t border-dashed border-blue-400"></div>
                        <div class="mx-2 flex h-7 w-7 items-center justify-center rounded-full bg-blue-500 text-lg leading-none text-white shadow-sm">
                            +
                        </div>
                        <div class="flex-1 border-t border-dashed border-blue-400"></div>
                    </div>
                </div>

                <livewire:cms::element-page-detail
                    wire:key="element-page-{{ $elementPage->id }}"
                    :modelId="$elementPage->id"
                >
                </livewire:cms::element-page-detail>
            </div>
        @endforeach
    </div>

    <div class="mt-8 mb-8 flex">
        <div class="mx-auto mt-4">
            <x-noerd::button @click="$modal('cms::element-picker-modal', { token: 'insert-end' })">
                {{ __('Add Element') }}
            </x-noerd::button>
        </div>
    </div>

@endif
