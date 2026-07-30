@php
    $selectedLayout = $page->layout ?? 'weblayout';
    // sanitize to blade component name (kebab-case not required here, we use file basename)
@endphp
<x-dynamic-component :component="'website::layouts.' . $selectedLayout">
    <div class="pt-48">
        <div class="mx-auto max-w-7xl px-6 pb-8">
            @foreach ($elements as $element)
                @php
                    $pageElementService = app(\Noerd\Website\Services\PageElementService::class);
                    $componentMap = $pageElementService->getComponentMapping();
                    $componentName = $componentMap[$element['key']] ?? null;
                @endphp

                @if ($componentName)
                    @livewire($componentName, ['data' => $element['data']], key('element-' . $loop->index))
                @else
                    <!-- Fallback for missing or invalid element template -->
                    <div class="rounded border border-gray-300 bg-gray-100 p-4">
                        <p class="text-sm text-gray-600">
                            Element component not found: {{ $element['key'] ?? 'unknown' }}
                        </p>
                        <pre class="mt-2 text-xs">{{ json_encode($element['data'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</x-dynamic-component>
