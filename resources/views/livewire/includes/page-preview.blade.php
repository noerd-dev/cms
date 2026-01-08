@if(count($this->livePreviewElements) > 0)
    @foreach($this->livePreviewElements as $element)
        @php
            $componentName = $this->componentMapping[$element['key']] ?? null;
            $componentExists = $componentName ? app(\Noerd\Website\Services\PageElementService::class)->elementDefinitionExists($componentName) : false;
        @endphp

        @if($componentExists)
            @livewire($componentName, ['data' => $element['data']], key('preview-element-' . $loop->index . '-' . $previewTick))
        @else
            <!-- Fallback for missing or invalid element template -->
            <div
                class="p-4 border border-red-300 mb-4 sm:p-8 relative overflow-hidden rounded-lg bg-red-50 after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-red-500/10">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-red-800">{{ __('Element component not found:') }} {{ $element['key'] ?? 'unknown' }}</p>
                        <p class="text-xs text-red-700 mt-2">{{ __('Please create both the .yml and .blade.php files in the elements folder.') }}</p>
                    </div>
                    <div>
                        <x-noerd::buttons.delete
                            wire:confirm="{{ __('Really delete element?') }}"
                            wire:click="deleteElement({{ (int) ($this->page->elements[$loop->index]->id ?? 0) }})"></x-noerd::buttons.delete>
                    </div>
                </div>
                <details class="mt-2">
                    <summary
                        class="text-xs text-red-600 cursor-pointer">{{ __('Show data') }}</summary>
                    <pre
                        class="text-xs mt-2 text-red-700">{{ json_encode($element['data'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                </details>
            </div>
        @endif
    @endforeach
@else
    <div class="text-center py-8 text-gray-500">
        <p>{{ __('No elements available') }}</p>
        <p class="text-sm mt-1">{{ __('Switch to content mode to add elements') }}</p>
    </div>
@endif
