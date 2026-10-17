@props(['config'])

@php
    // UI strings of the editor (field renderers, menus) — translated here,
    // the JavaScript only displays them.
    $config['strings'] = [
        'placeholder' => __('Type // for elements'),
        'textGroup' => __('Text'),
        'heading1' => __('Heading 1'),
        'heading2' => __('Heading 2'),
        'heading3' => __('Heading 3'),
        'paragraph' => __('Text'),
        'bulletList' => __('Bullet List'),
        'orderedList' => __('Numbered List'),
        'quote' => __('Blockquote'),
        'divider' => __('Horizontal Line'),
        'nothingFound' => __('No element found'),
        'drag' => __('Drag to move'),
        'duplicate' => __('Duplicate'),
        'delete' => __('Delete'),
        'chooseImage' => __('Choose image'),
        'removeImage' => __('Remove'),
        'noImage' => __('No image'),
        'saveFirst' => __('Please save the entry first to manage its entries.'),
        'entry' => __('Entry'),
        'entries' => __('Entries'),
        'manage' => __('Manage'),
        'unsupportedField' => __('The field type :type cannot be edited here yet.'),
        'unknownElement' => __('Element component not found:') . ' :key',
    ];
@endphp

{{-- The content editor: running text with elements in between, ONE editor.
     wire:ignore — the server talks to it through browser events only. --}}
<div
    wire:ignore
    x-data="cmsDocumentEditor(@js($config))"
    x-on:content-document-load.window="load($event.detail)"
    x-on:content-document-saved.window="saved($event.detail)"
    :class="{ 'cms-doc--collapsed': collapsed }"
    class="cms-doc rounded-lg border border-gray-300 bg-white"
>
    @if ($config['editable'])
        <div class="sticky top-0 z-10 flex flex-wrap items-center gap-0.5 rounded-t-lg border-b border-gray-300 bg-gray-50 p-1">
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleHeading({ level: 1 }).run()"
        :class="{ 'bg-gray-200': isActive('heading', { level: 1 }) }"
        class="rounded p-1.5 text-xs font-bold transition-colors hover:bg-gray-200"
        title="{{ __('Heading 1') }}"
        aria-label="{{ __('Heading 1') }}"
    >H1</button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleHeading({ level: 2 }).run()"
        :class="{ 'bg-gray-200': isActive('heading', { level: 2 }) }"
        class="rounded p-1.5 text-xs font-bold transition-colors hover:bg-gray-200"
        title="{{ __('Heading 2') }}"
        aria-label="{{ __('Heading 2') }}"
    >H2</button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleHeading({ level: 3 }).run()"
        :class="{ 'bg-gray-200': isActive('heading', { level: 3 }) }"
        class="rounded p-1.5 text-xs font-bold transition-colors hover:bg-gray-200"
        title="{{ __('Heading 3') }}"
        aria-label="{{ __('Heading 3') }}"
    >H3</button>
    <div class="mx-1 h-6 w-px bg-gray-300"></div>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleBold().run()"
        :class="{ 'bg-gray-200': isActive('bold') }"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Bold') }}"
        aria-label="{{ __('Bold') }}"
    ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path></svg></button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleItalic().run()"
        :class="{ 'bg-gray-200': isActive('italic') }"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Italic') }}"
        aria-label="{{ __('Italic') }}"
    ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0v16m-4 0h8"></path></svg></button>
    <div class="mx-1 h-6 w-px bg-gray-300"></div>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleBulletList().run()"
        :class="{ 'bg-gray-200': isActive('bulletList') }"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Bullet List') }}"
        aria-label="{{ __('Bullet List') }}"
    ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleOrderedList().run()"
        :class="{ 'bg-gray-200': isActive('orderedList') }"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Numbered List') }}"
        aria-label="{{ __('Numbered List') }}"
    ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h10M3 8h.01M3 12h.01M3 16h.01"></path></svg></button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleBlockquote().run()"
        :class="{ 'bg-gray-200': isActive('blockquote') }"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Blockquote') }}"
        aria-label="{{ __('Blockquote') }}"
    ><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5c-1.073 0-2.099-.49-2.748-1.179z" /></svg></button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().setHorizontalRule().run()"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Horizontal Line') }}"
        aria-label="{{ __('Horizontal Line') }}"
    ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"></path></svg></button>
    <div class="mx-1 h-6 w-px bg-gray-300"></div>

            <div class="relative">
                <button
                    type="button"
                    @mousedown.prevent
                    @click.prevent="showLinkInput = ! showLinkInput"
                    :class="{ 'bg-gray-200': isActive('link') }"
                    class="rounded p-1.5 transition-colors hover:bg-gray-200"
                    title="{{ __('Link') }}"
                    aria-label="{{ __('Link') }}"
                ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg></button>
                <div
                    x-show="showLinkInput"
                    x-cloak
                    @click.outside="showLinkInput = false"
                    class="absolute top-full left-0 z-20 mt-1 flex gap-2 rounded-md border border-gray-300 bg-white p-2 shadow-lg"
                >
                    <input type="url" x-model="linkUrl" placeholder="https://..." class="w-48 rounded border border-gray-300 px-2 py-1 text-sm" @keydown.enter.prevent="setLink()" />
                    <button type="button" @click.prevent="setLink()" class="rounded bg-brand-primary px-2 py-1 text-sm text-brand-primary-text hover:opacity-90">{{ __('OK') }}</button>
                    <button type="button" x-show="isActive('link')" @click.prevent="removeLink(); showLinkInput = false" class="rounded bg-red-600 px-2 py-1 text-sm text-white hover:bg-red-700">{{ __('Remove link') }}</button>
                </div>
            </div>

            <div class="ml-auto flex items-center gap-1">
                <button type="button" @mousedown.prevent @click.prevent="insertMenu()" class="rounded px-2 py-1 text-xs font-semibold transition-colors hover:bg-gray-200">
                    + {{ __('Element') }}
                </button>
                <button type="button" @click.prevent="toggleCollapsed()" class="rounded px-2 py-1 text-xs transition-colors hover:bg-gray-200"
                    x-text="collapsed ? '{{ __('Expand elements') }}' : '{{ __('Collapse elements') }}'"></button>
            </div>
        </div>

        <div x-ref="bubble" class="cms-doc-bubble" style="visibility: hidden; position: absolute;">
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleBold().run()"
        :class="{ 'bg-gray-200': isActive('bold') }"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Bold') }}"
        aria-label="{{ __('Bold') }}"
    ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path></svg></button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleItalic().run()"
        :class="{ 'bg-gray-200': isActive('italic') }"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Italic') }}"
        aria-label="{{ __('Italic') }}"
    ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0v16m-4 0h8"></path></svg></button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleHeading({ level: 1 }).run()"
        :class="{ 'bg-gray-200': isActive('heading', { level: 1 }) }"
        class="rounded p-1.5 text-xs font-bold transition-colors hover:bg-gray-200"
        title="{{ __('Heading 1') }}"
        aria-label="{{ __('Heading 1') }}"
    >H1</button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="command().toggleHeading({ level: 2 }).run()"
        :class="{ 'bg-gray-200': isActive('heading', { level: 2 }) }"
        class="rounded p-1.5 text-xs font-bold transition-colors hover:bg-gray-200"
        title="{{ __('Heading 2') }}"
        aria-label="{{ __('Heading 2') }}"
    >H2</button>
    <button
        type="button"
        @mousedown.prevent
        @click.prevent="showLinkInput = true"
        :class="{ 'bg-gray-200': isActive('link') }"
        class="rounded p-1.5 transition-colors hover:bg-gray-200"
        title="{{ __('Link') }}"
        aria-label="{{ __('Link') }}"
    ><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg></button>
        </div>
    @endif

    <div x-ref="editor"></div>
</div>
