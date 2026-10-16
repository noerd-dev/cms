{{--
    The element builder of a content. Expects $content (the edited record's
    content or null) and optionally $show (false hides the builder). The
    hosting component uses Noerd\Cms\Traits\EditsContentElements.
--}}
@if (($show ?? true) && $content?->id)
    <div wire:sort="elementSort">
        @foreach ($content->elements()->get() as $loopIndex => $element)
            <div wire:sort:item="{{ $element->id }}" wire:key="sort-item-{{ $element->id }}">
                <div
                    class="group/insert relative z-10 -my-3 flex h-6 cursor-pointer items-center"
                    @click="$modal('cms::element-picker-modal', { token: 'insert-at-{{ $loopIndex }}', contentType: '{{ $content->type }}' })"
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
                    wire:key="element-page-{{ $element->id }}"
                    :modelId="$element->id"
                >
                </livewire:cms::element-page-detail>
            </div>
        @endforeach
    </div>

    <div class="mt-8 mb-8 flex">
        <div class="mx-auto mt-4">
            <x-noerd::button @click="$modal('cms::element-picker-modal', { token: 'insert-end', contentType: '{{ $content->type }}' })">
                {{ __('Add Element') }}
            </x-noerd::button>
        </div>
    </div>

@endif
