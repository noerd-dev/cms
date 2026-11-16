@php
    $selectedLayout = $page->layout ?? 'weblayout';
    // Only a plain layout name — the value is stored page data.
    if (! is_string($selectedLayout) || ! preg_match('/^[A-Za-z0-9_-]+$/', $selectedLayout)) {
        $selectedLayout = 'weblayout';
    }
    $componentMap = app(\Noerd\Website\Services\PageElementService::class)->getComponentMapping();
@endphp
<x-dynamic-component :component="'website::layouts.' . $selectedLayout" :meta="$meta ?? []">
    <div class="pt-48">
        <div class="mx-auto max-w-7xl px-6 pb-8">
            @foreach ($elements as $element)
                @php
                    $componentName = $componentMap[$element['key']] ?? null;
                @endphp

                @if ($componentName)
                    @livewire($componentName, ['data' => $element['data']], key('element-' . $loop->index))
                @elseif (app()->isLocal() || config('app.debug'))
                    {{-- Only while developing: a visitor never sees element data of a missing template. --}}
                    <div class="rounded border border-gray-300 bg-gray-100 p-4">
                        <p class="text-sm text-gray-600">
                            Element component not found: {{ $element['key'] ?? 'unknown' }}
                        </p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</x-dynamic-component>
