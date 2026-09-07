<?php

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\CollectionEntryStore;
use Noerd\Cms\Services\PageElementEditorService;
use Noerd\Cms\Services\PageSlugService;
use Noerd\Cms\Support\PageLayouts;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Facades\Noerd;
use Noerd\Helpers\TenantHelper;
use Noerd\Media\Models\Media;
use Noerd\Media\Services\MediaUploadService;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Traits\NoerdDetail;

/*
 | The page editor: a plain page or a collection entry (when a collectionKey is
 | given) with the element builder below the form. Slugs, collection entries
 | and element mutations are delegated to their services; this component only
 | owns the form state and the editor chrome.
 */
new class extends Component
{
    use LanguageFilterTrait;
    use NoerdDetail;
    use WithFileUploads;

    public ?string $detailPrimary = 'pageId';

    public $detailModel = Page::class;

    public ?array $collectionLayout = null;

    public ?string $collectionKey = null;

    public array $collectionData = [];

    public array $images = [];

    /**
     * Correlates a media-picker round trip: only the modal opened by THIS
     * component instance may write the selected media back. Kept out of
     * $detailData so it can never leak into the mass-assigned payload.
     */
    #[Locked]
    public ?string $mediaToken = null;

    public $lastChangeTime;

    #[Computed]
    public function hasPageFeatures(): bool
    {
        if (! $this->collectionKey || ! $this->collectionLayout) {
            return true;
        }

        return $this->collectionLayout['hasPage'] ?? true;
    }

    /**
     * The edited page, resolved through the tenant scope — null for a new
     * page and for a page of another tenant.
     */
    #[Computed]
    public function pageModel(): ?Page
    {
        if (! $this->modelId) {
            return null;
        }

        return Page::with('elements')->find($this->modelId);
    }

    #[Computed]
    public function elements()
    {
        return FieldHelper::getAllElements();
    }

    public function mount(?string $collectionKey = null): void
    {
        $this->initDetail();

        $this->collectionKey = $collectionKey ?? $this->collectionKey;

        $page = new Page;
        if ($this->modelId) {
            $page = Page::find($this->modelId) ?? new Page;
        }

        // Auto-detect collectionKey from page's collection if not explicitly provided
        if (! $this->collectionKey && $page->collection_id && $page->collection) {
            $this->collectionKey = mb_strtolower($page->collection->collection_key);
        }

        if ($this->collectionKey) {
            $this->collectionLayout = CollectionHelper::getCollectionFields($this->collectionKey);
        }

        $this->mountPageDetail($page);
        $this->mergeCollectionData($page);

        // Populate relationTitles for saved page relations defined in the collection layout
        foreach ($this->collectionLayout['fields'] ?? [] as $field) {
            if (($field['type'] ?? null) !== 'relation' || ($field['modalComponent'] ?? null) !== 'pages-list') {
                continue;
            }
            $fieldName = str_replace('detailData.', '', $field['name'] ?? '');
            $relatedPage = ! empty($this->detailData[$fieldName] ?? null) ? Page::find($this->detailData[$fieldName]) : null;
            if ($relatedPage) {
                $this->relationTitles[$fieldName] = RelationFieldDefinition::normalizeDisplayValue($relatedPage->name);
            }
        }
    }

    protected function mountPageDetail(?Page $page = null): void
    {
        $page ??= new Page;

        $this->detailData = $page->toArray();
        $this->detailData['custom_attributes'] = $this->detailData['custom_attributes'] ?? [];

        if (empty($this->detailData['layout'])) {
            $this->detailData['layout'] = PageLayouts::default();
        }

        $activeLangCodes = $this->activeLanguageCodes();

        foreach (['name', 'slug'] as $field) {
            if (! is_array($this->detailData[$field] ?? null) || empty($this->detailData[$field])) {
                $this->detailData[$field] = array_fill_keys($activeLangCodes, '');

                continue;
            }

            foreach ($activeLangCodes as $lang) {
                if (! is_string($this->detailData[$field][$lang] ?? null)) {
                    $this->detailData[$field][$lang] = '';
                }
            }
        }

        $this->lastChangeTime = time();
    }

    /**
     * Collection entries keep their fields in the `data` column — merge them
     * into $detailData for wire:model binding.
     */
    private function mergeCollectionData(Page $page): void
    {
        if (! $this->collectionKey || ! is_array($page->data)) {
            $this->detailData['sort'] ??= $page->sort ?? 0;

            return;
        }

        $this->collectionData = $page->data;
        foreach ($page->data as $key => $value) {
            if (! str_starts_with($key, 'detailData.') && ! in_array($key, ['name', 'slug', 'layout', 'sort'], true)) {
                $this->detailData[$key] = $value;
            }
        }

        $this->detailData['sort'] ??= $page->sort ?? 0;
    }

    /**
     * Options of the YAML `layout` picklist (`picklistField: layoutOptions`).
     */
    public function layoutOptions(): array
    {
        return PageLayouts::options();
    }

    public function generateSlug(string $name, ?string $languageCode = null): string
    {
        return app(PageSlugService::class)->generate($name, $languageCode, $this->defaultLanguageCode());
    }

    public function updated($propertyName, $value): void
    {
        // Only a NEW page derives its slug from the name; saved pages keep their URL.
        if (! str_starts_with($propertyName, 'detailData.name.') || $this->modelId || empty($value)) {
            return;
        }

        $language = str_replace('detailData.name.', '', $propertyName);

        if (! is_array($this->detailData['slug'] ?? null)) {
            $this->detailData['slug'] = array_fill_keys($this->activeLanguageCodes(), '');
        }

        $this->detailData['slug'][$language] = app(PageSlugService::class)->uniqueFor(
            (string) $value,
            $language,
            $this->defaultLanguageCode(),
            (int) TenantHelper::currentTenantId(),
        );
    }

    public function updatedImages(): void
    {
        $mediaUploadService = app()->make(MediaUploadService::class);
        foreach ($this->images as $key => $image) {
            $media = $mediaUploadService->storeFromUploadedFile($image);
            $this->detailData[$key] = $this->urlWithoutDomain($media);
        }
    }

    public function deleteImage(string $fieldName): void
    {
        $this->detailData[$fieldName] = null;
    }

    public function openSelectMediaModal(string $fieldName): void
    {
        $this->mediaToken = uniqid('media_', true);
        Noerd::modal('media::media-list', ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $this->mediaToken]);
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if ($this->mediaToken === null || $this->mediaToken !== $token) {
            return;
        }
        $media = Media::find($mediaId);
        if (! $media) {
            return;
        }
        $this->detailData[$fieldName ?? 'image'] = $this->urlWithoutDomain($media);
        $this->mediaToken = null;
    }

    #[On('pageSelected')]
    public function pageSelected($value, $context): void
    {
        $page = Page::find($value);
        if (! $page) {
            return;
        }

        $fieldName = str_replace('detailData.', '', $context);

        $this->relationTitles[$fieldName] = RelationFieldDefinition::normalizeDisplayValue($page->name);
        $this->detailData[$fieldName] = $page->id;
    }

    /*
     | Element builder — every mutation runs through the editor service on the
     | tenant-scoped page and is guarded like store(): WriteGuardHook only
     | covers store()/delete(), so these check the write permission themselves.
     */

    #[On('elementPicked')]
    public function addElement($elementKey, $token = 'insert-end'): void
    {
        if (! $this->canSaveObject() || ! $this->pageModel) {
            return;
        }

        $position = str_starts_with((string) $token, 'insert-at-') ? (int) substr((string) $token, 10) : null;

        $this->elementEditor()->addElement($this->pageModel, (string) $elementKey, $position);
        $this->lastChangeTime = time();
    }

    public function elementSort($elementId, $newPosition): void
    {
        if (! $this->canSaveObject() || ! $this->pageModel) {
            return;
        }

        $this->elementEditor()->move($this->pageModel, (int) $elementId, (int) $newPosition);
        $this->lastChangeTime = time();
    }

    public function duplicateElement(int $elementPageId): void
    {
        if (! $this->canSaveObject() || ! $this->pageModel) {
            return;
        }

        if ($this->elementEditor()->duplicate($this->pageModel, $elementPageId)) {
            $this->lastChangeTime = time();
            $this->dispatch('reloadPageComponent');
        }
    }

    public function deleteElement(int $elementPageId): void
    {
        if (! $this->canSaveObject() || ! $this->pageModel) {
            return;
        }

        if ($this->elementEditor()->delete($this->pageModel, $elementPageId)) {
            $this->lastChangeTime = time();
            $this->dispatch('reloadPageComponent');
        }
    }

    #[On('reloadPageComponent')]
    public function reloadPage(): void
    {
        $this->lastChangeTime = time();
    }

    #[On('languageChanged')]
    public function onLanguageChanged(): void
    {
        // The roundtrip re-renders the translatable inputs against the new language.
    }

    public function getPageUrl(): ?string
    {
        $slug = $this->detailData['slug'][$this->selectedLanguageCode()] ?? null;

        return $slug ? url($slug) : null;
    }

    /**
     * URLs of the YAML `url:` actions — the "View page" button only appears
     * once the page has a URL in the selected language.
     *
     * @return array<string, string>
     */
    public function detailActionUrls(): array
    {
        if (! $this->hasPageFeatures) {
            return [];
        }

        $url = $this->getPageUrl();

        return $url ? ['pageUrl' => $url] : [];
    }

    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        if ($this->collectionKey) {
            $this->storeCollectionPage();

            return;
        }

        $this->resetValidation();
        $errors = app(CollectionEntryStore::class)->requiredFieldErrors($this->detailData, $this->defaultLanguageCode());

        foreach ($errors as $field => $message) {
            $this->addError($field, $message);
        }

        if ($errors !== []) {
            return;
        }

        $data = $this->detailData;
        $data['tenant_id'] = TenantHelper::currentTenantId();
        $data['layout'] = ! empty($data['layout']) ? $data['layout'] : PageLayouts::default();
        $data['slug'] = array_filter(is_array($this->detailData['slug'] ?? null) ? $this->detailData['slug'] : [], fn($slug): bool => ! empty($slug));
        $data['name'] = $this->detailData['name'];

        $page = Page::updateOrCreate(['id' => $this->modelId], $data);

        $this->dispatch('storeElements');

        $this->storeProcess($page);
    }

    private function storeCollectionPage(): void
    {
        $store = app(CollectionEntryStore::class);

        if ($this->collectionLayout['hasPage'] ?? true) {
            $this->resetValidation();
            $errors = $store->requiredFieldErrors($this->detailData, $this->defaultLanguageCode());

            foreach ($errors as $field => $message) {
                $this->addError($field, $message);
            }

            if ($errors !== []) {
                return;
            }
        }

        $page = $store->persist(
            (string) $this->collectionKey,
            $this->collectionLayout,
            $this->detailData,
            $this->modelId ? (int) $this->modelId : null,
            (int) TenantHelper::currentTenantId(),
            auth()->id(),
            $this->defaultLanguageCode(),
        );

        $this->modelId = $page->id;

        $this->storeProcess($page);

        $this->dispatch('storeElements');
    }

    public function copy(): void
    {
        if (! $this->canSaveObject() || ! $this->pageModel) {
            return;
        }

        $newPage = $this->elementEditor()->copyPage($this->pageModel);

        $this->modelId = $newPage->id;
        $this->mountPageDetail($newPage);
        $this->mergeCollectionData($newPage);

        $this->dispatch('listRefresh');
        $this->dispatch('refreshList-pages-list');
        $this->showSuccessIndicator = true;
    }

    private function elementEditor(): PageElementEditorService
    {
        return app(PageElementEditorService::class);
    }

    private function urlWithoutDomain(Media $media): string
    {
        $url = Storage::disk($media->disk)->url($media->path);

        return mb_strstr($url, '/storage');
    }
} ?>

<x-noerd::page>

    <x-slot:header>
        <x-noerd::modal-title>
            {{ $collectionLayout['title'] ?? __('Page') }}

            <x-slot:actions>
                @if($this->pageModel?->id && $this->hasPageFeatures)
                    <x-noerd::button
                        variant="secondary"
                        type="button"
                        x-data
                        x-init="if (!Alpine.store('elements')) Alpine.store('elements', { collapsed: false })"
                        @click="$store.elements.collapsed = !$store.elements.collapsed"
                        x-text="$store.elements.collapsed ? '{{ __('Expand elements') }}' : '{{ __('Collapse elements') }}'"
                    ></x-noerd::button>
                @endif

                <livewire:cms::language-switcher/>
            </x-slot:actions>
        </x-noerd::modal-title>
    </x-slot:header>
    <div>
        @if($collectionLayout)
            {{-- Sort position of the entry inside its collection --}}
            <div class="flex">
                <div class="flex ml-auto items-center my-6 space-x-4">
                    <div class="flex ml-auto items-center space-x-2">
                        <label for="sort" class="text-sm text-gray-600 font-medium">{{ __('Sort') }}:</label>
                        <input
                            wire:model="detailData.sort"
                            id="sort"
                            type="number"
                            min="0"
                            step="1"
                            class="w-16 rounded-md border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                        />
                    </div>
                </div>
            </div>

            {{-- Collection fields --}}
            <div class="p-4 border border-blue-200 mb-4 relative overflow-hidden rounded-lg bg-blue-50 after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-blue-950/5 bg-[image:radial-gradient(var(--pattern-fg)_1px,_transparent_0)] bg-[size:10px_10px] bg-fixed [--pattern-fg:var(--color-blue-950)]/5">
                @include('noerd::components.detail.block', array_merge($collectionLayout, ['model' => $detailData]))
            </div>
        @endif

        @php
            $effectiveLayout = $this->hasPageFeatures ? $pageLayout : array_merge($pageLayout, ['tabs' => []]);
        @endphp
        <x-noerd::tab-content :layout="$effectiveLayout" :showBlock="$this->hasPageFeatures" :model="$detailData">
            <x-slot:tab1>
                @include('cms::partials.page-elements', ['hasPageFeatures' => $this->hasPageFeatures])
            </x-slot:tab1>
        </x-noerd::tab-content>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>

</x-noerd::page>
