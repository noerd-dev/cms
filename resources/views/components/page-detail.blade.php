<?php

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\FieldTypeConverter;
use Noerd\Facades\Noerd;
use Noerd\Media\Models\Media;
use Noerd\Media\Services\MediaUploadService;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;
    use WithFileUploads;

    #[Url(as: 'pageId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = Page::class;

    public const DETAIL_COMPONENT = 'cms::page-detail';

    public ?array $collectionLayout = null;

    public ?string $collectionKey = null;

    public array $collectionData = [];

    public array $images = [];

    public string $hrefPage = '';

    public $lastChangeTime;

    #[Computed]
    public function hasPageFeatures(): bool
    {
        if (! $this->collectionKey || ! $this->collectionLayout) {
            return true;
        }

        return $this->collectionLayout['hasPage'] ?? true;
    }

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

        // For collection entries: Merge data from the data column into detailData for proper wire:model binding
        if ($this->collectionKey && isset($this->detailData['data']) && is_array($this->detailData['data'])) {
            $this->collectionData = $this->detailData['data'];
            foreach ($this->detailData['data'] as $key => $value) {
                if (! str_starts_with($key, 'detailData.') && ! in_array($key, ['name', 'slug', 'layout', 'sort'], true)) {
                    $this->detailData[$key] = $value;
                }
            }
        }

        // Populate relationTitles for saved page relations defined in the collection layout
        foreach ($this->collectionLayout['fields'] ?? [] as $field) {
            if (($field['type'] ?? null) !== 'relation') {
                continue;
            }
            if (($field['modalComponent'] ?? null) !== 'pages-list') {
                continue;
            }
            $fieldName = str_replace('detailData.', '', $field['name'] ?? '');
            $relatedId = $this->detailData[$fieldName] ?? null;
            if (empty($relatedId)) {
                continue;
            }
            $relatedPage = Page::find($relatedId);
            if (! $relatedPage) {
                continue;
            }
            $this->relationTitles[$fieldName] = is_array($relatedPage->name)
                ? ($relatedPage->name[session('selectedLanguage')] ?? array_values($relatedPage->name)[0] ?? '')
                : $relatedPage->name;
        }

        // Ensure sort field is available for collections
        $this->detailData['sort'] ??= $page->sort ?? 0;
    }

    protected function mountPageDetail(?Page $page = null): void
    {
        if (! $page) {
            $page = new Page;
        }

        $this->detailData = $page->toArray();
        $this->detailData['custom_attributes'] = $this->detailData['custom_attributes'] ?? [];

        $availableLayouts = $this->layoutOptions();
        if (! isset($this->detailData['layout']) || empty($this->detailData['layout'])) {
            $this->detailData['layout'] = array_key_first($availableLayouts);
        }

        $activeLangCodes = $this->getActiveTenantLanguageCodes();
        if (empty($activeLangCodes)) {
            $activeLangCodes = [$this->getDefaultLanguageCode()];
        }

        foreach (['name', 'slug'] as $field) {
            if (isset($this->detailData[$field])) {
                if (! is_array($this->detailData[$field]) || empty($this->detailData[$field])) {
                    $this->detailData[$field] = $this->initializeEmptySlugArray();
                } else {
                    foreach ($activeLangCodes as $lang) {
                        if (! isset($this->detailData[$field][$lang]) || ! is_string($this->detailData[$field][$lang])) {
                            $this->detailData[$field][$lang] = '';
                        }
                    }
                }
            } else {
                $this->detailData[$field] = $this->initializeEmptySlugArray();
            }
        }

        $this->lastChangeTime = time();
    }

    public function layoutOptions(): array
    {
        $layoutsDirectory = base_path('app-modules/website/resources/views/components/layouts');
        $options = [];

        if (is_dir($layoutsDirectory)) {
            foreach (glob($layoutsDirectory.'/*.blade.php') as $filePath) {
                $fileName = basename($filePath, '.blade.php');

                if (str_starts_with($fileName, '_')) {
                    continue;
                }

                $options[$fileName] = $fileName;
            }
        }

        if (empty($options)) {
            $options['weblayout'] = 'weblayout';
        }

        return $options;
    }

    public function getDefaultLanguageCode(): string
    {
        $defaultLanguage = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_default', true)
            ->first();

        return $defaultLanguage?->code ?? 'en';
    }

    public function getActiveTenantLanguageCodes(): array
    {
        return CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->pluck('code')
            ->toArray();
    }

    public function generateSlug(string $name, ?string $languageCode = null): string
    {
        $slug = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $name);
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = mb_trim($slug, '-');

        if ($languageCode && $languageCode !== $this->getDefaultLanguageCode()) {
            $slug = $languageCode.'/'.$slug;
        }

        return '/'.$slug;
    }

    private function ensureUniqueSlug(string $slug, string $languageCode): string
    {
        $tenantId = auth()->user()->selected_tenant_id;
        $originalSlug = $slug;
        $counter = 2;

        while (true) {
            $query = Page::where('tenant_id', $tenantId)
                ->whereJsonContains("slug->{$languageCode}", $slug);

            if ($this->modelId) {
                $query->where('id', '!=', $this->modelId);
            }

            if (! $query->exists()) {
                return $slug;
            }

            $slug = $originalSlug.'-'.$counter;

            $counter++;
        }
    }

    public function updated($propertyName, $value): void
    {
        if (! str_starts_with($propertyName, 'detailData.name.')) {
            return;
        }

        if ($this->modelId) {
            return;
        }

        $language = str_replace('detailData.name.', '', $propertyName);

        if (! empty($value)) {
            if (! isset($this->detailData['slug']) || ! is_array($this->detailData['slug'])) {
                $this->detailData['slug'] = $this->initializeEmptySlugArray();
            }

            $generatedSlug = $this->generateSlug($value, $language);
            $this->detailData['slug'][$language] = $this->ensureUniqueSlug($generatedSlug, $language);
        }
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
        $token = uniqid('media_', true);
        $this->detailData['__mediaToken'] = $token;
        Noerd::modal('media::media-list', ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $token]);
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if (($this->detailData['__mediaToken'] ?? null) !== $token) {
            return;
        }
        $media = Media::find($mediaId);
        if (! $media) {
            return;
        }
        $this->detailData[$fieldName ?? 'image'] = $this->urlWithoutDomain($media);
        unset($this->detailData['__mediaToken']);
    }

    #[On('elementPicked')]
    public function addElement($elementKey, $token = 'insert-end'): void
    {
        if (str_starts_with($token, 'insert-at-')) {
            $position = (int) str_replace('insert-at-', '', $token);

            ElementPage::where('page_id', $this->modelId)
                ->where('sort', '>=', $position)
                ->increment('sort');

            $sort = $position;
        } else {
            $sortElement = ElementPage::where('page_id', $this->modelId)
                ->orderBy('sort', 'desc')
                ->first();

            $sort = ($sortElement?->sort ?? 0) + 1;
        }

        ElementPage::create([
            'page_id' => $this->modelId,
            'element_key' => $elementKey,
            'sort' => $sort,
            'data' => '{}',
        ]);

        $this->lastChangeTime = time();
    }

    #[On('pageSelected')]
    public function pageSelected($value, $context): void
    {
        $page = Page::find($value);
        if (! $page) {
            return;
        }

        $fieldName = str_replace('detailData.', '', $context);
        $name = is_array($page->name)
            ? ($page->name[session('selectedLanguage')] ?? array_values($page->name)[0] ?? '')
            : $page->name;

        $this->hrefPage = $name;
        $this->relationTitles[$fieldName] = $name;
        $this->detailData[$fieldName] = $page->id;
    }

    public function elementSort($elementId, $newPosition): void
    {
        \Log::info('elementSort called', ['elementId' => $elementId, 'newPosition' => $newPosition, 'type_id' => gettype($elementId), 'type_pos' => gettype($newPosition)]);

        $elementId = (int) $elementId;
        $newPosition = (int) $newPosition;

        $elements = ElementPage::where('page_id', $this->modelId)
            ->orderBy('sort')
            ->get();
        $loop = 0;
        foreach ($elements as $element) {
            if ($newPosition === $loop) {
                $loop++;
            }
            if ($element->id === $elementId) {
                $element->sort = $newPosition;
                $element->save();
            } else {
                $element->sort = $loop++;
                $element->save();
            }
        }
        $this->lastChangeTime = time();
    }

    public function duplicateElement(int $elementPageId): void
    {
        $element = ElementPage::find($elementPageId);
        if (! $element || (int) $element->page_id !== (int) $this->modelId) {
            return;
        }

        ElementPage::where('page_id', $this->modelId)
            ->where('sort', '>', $element->sort)
            ->increment('sort');

        ElementPage::create([
            'page_id' => $this->modelId,
            'element_key' => $element->element_key,
            'sort' => $element->sort + 1,
            'data' => $element->data,
        ]);

        $this->lastChangeTime = time();
        $this->dispatch('reloadPageComponent');
    }

    public function deleteElement(int $elementPageId): void
    {
        $element = ElementPage::find($elementPageId);
        if ($element && (int) $element->page_id === (int) $this->modelId) {
            $element->delete();
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
    public function refresh(): void
    {
        $this->dispatch('$refresh');
    }

    public function openElements(): void
    {
        Noerd::modal('cms::element-page-detail', ['elementPageId' => $this->modelId]);
    }

    public function getPageUrl(): ?string
    {
        $selectedLanguage = session('selectedLanguage') ?? $this->getDefaultLanguageCode();
        $slug = $this->detailData['slug'][$selectedLanguage] ?? null;

        if (! $slug) {
            return null;
        }

        return url($slug);
    }

    public function store(): void
    {
        if ($this->collectionKey) {
            $this->storeCollectionPage();

            return;
        }

        $defaultLang = $this->getDefaultLanguageCode();

        $this->resetValidation();
        $hasErrors = false;

        if (empty($this->detailData['name'][$defaultLang] ?? '')) {
            $this->addError('detailData.name', __('validation.required', ['attribute' => __('Title')]));
            $hasErrors = true;
        }

        if (empty($this->detailData['slug'][$defaultLang] ?? '')) {
            $this->addError('detailData.slug', __('validation.required', ['attribute' => __('URL')]));
            $hasErrors = true;
        }

        if ($hasErrors) {
            return;
        }

        $data = $this->detailData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;

        if (empty($data['layout'])) {
            $data['layout'] = array_key_first($this->layoutOptions());
        }

        $cleanSlugData = [];
        if (isset($this->detailData['slug']) && is_array($this->detailData['slug'])) {
            foreach ($this->detailData['slug'] as $lang => $slug) {
                if (! empty($slug)) {
                    $cleanSlugData[$lang] = $slug;
                }
            }
        }

        $data['slug'] = $cleanSlugData;
        $data['name'] = $this->detailData['name'];

        $page = Page::updateOrCreate(
            ['id' => $this->modelId],
            $data,
        );

        $this->dispatch('storeElements');

        $this->storeProcess($page);
    }

    public function delete(): void
    {
        $page = Page::find($this->modelId);
        $page->delete();
        $this->closeModalProcess($this->getListComponent());
    }

    public function copy(): void
    {
        $sourcePage = Page::with('elements')->find($this->modelId);
        if (! $sourcePage) {
            return;
        }

        $newName = [];
        foreach ($sourcePage->name ?? [] as $lang => $name) {
            $newName[$lang] = ! empty($name) ? $name.' 2' : $name;
        }

        $newSlug = [];
        foreach ($sourcePage->slug ?? [] as $lang => $slug) {
            if (! empty($slug)) {
                $newSlug[$lang] = $this->ensureUniqueSlug($slug.'-2', $lang);
            }
        }

        $newPage = $sourcePage->replicate(['id']);
        $newPage->name = $newName;
        $newPage->slug = $newSlug;

        if ($this->collectionKey && is_array($newPage->data)) {
            $newData = $newPage->data;
            if (isset($newData['title'])) {
                if (is_array($newData['title'])) {
                    foreach ($newData['title'] as $lang => $value) {
                        if (! empty($value)) {
                            $newData['title'][$lang] = $value.' 2';
                        }
                    }
                } elseif (is_string($newData['title']) && ! empty($newData['title'])) {
                    $newData['title'] = $newData['title'].' 2';
                }
            }
            $newPage->data = $newData;
        }

        $newPage->save();

        foreach ($sourcePage->elements as $element) {
            ElementPage::create([
                'page_id' => $newPage->id,
                'element_key' => $element->element_key,
                'sort' => $element->sort,
                'data' => $element->data,
            ]);
        }

        $this->modelId = $newPage->id;
        $this->mountPageDetail($newPage);

        if ($this->collectionKey && isset($newPage->data) && is_array($newPage->data)) {
            foreach ($newPage->data as $key => $value) {
                if (! str_starts_with($key, 'detailData.') && ! in_array($key, ['name', 'slug', 'layout', 'sort'], true)) {
                    $this->detailData[$key] = $value;
                }
            }
        }

        $this->detailData['sort'] ??= $newPage->sort ?? 0;
        $this->lastChangeTime = time();

        $this->dispatch('listRefresh');
        $this->showSuccessIndicator = true;
    }

    /**
     * Get collection field names from the YAML definition (without detailData. prefix).
     */
    private function getCollectionFieldNames(): array
    {
        if (! $this->collectionLayout || ! isset($this->collectionLayout['fields'])) {
            return [];
        }

        $fieldNames = [];
        foreach ($this->collectionLayout['fields'] as $field) {
            $name = $field['name'] ?? '';
            $name = str_replace('detailData.', '', $name);
            if (! empty($name)) {
                $fieldNames[] = $name;
            }
        }

        return $fieldNames;
    }

    /**
     * Extract only collection-specific fields from detailData.
     */
    private function extractCollectionData(): array
    {
        $collectionFieldNames = $this->getCollectionFieldNames();
        $collectionData = [];

        foreach ($collectionFieldNames as $fieldName) {
            if (array_key_exists($fieldName, $this->detailData)) {
                $collectionData[$fieldName] = $this->detailData[$fieldName];
            }
        }

        return $collectionData;
    }

    private function storeCollectionPage(): void
    {
        // Pull the display name from the definition repository when available
        // so it matches what the user configured.
        $definition = app(CollectionDefinitionRepositoryContract::class)->find($this->collectionKey);
        $parentCollection = Collection::firstOrCreate([
            'tenant_id' => auth()->user()->selected_tenant_id,
            'collection_key' => mb_strtoupper($this->collectionKey),
        ], [
            'name' => $definition?->titleList ?: ucfirst($this->collectionKey),
            'created_by' => auth()->id(),
        ]);

        $hasPageFeatures = $this->collectionLayout['hasPage'] ?? true;

        $rawCollectionData = $this->extractCollectionData();
        $convertedCollectionData = FieldTypeConverter::convertCollectionData($rawCollectionData, $this->collectionKey);

        $data = [
            'tenant_id' => auth()->user()->selected_tenant_id,
            'collection_id' => $parentCollection->id,
            'data' => $convertedCollectionData,
            'sort' => (int) ($this->detailData['sort'] ?? 0),
        ];

        $availableLayouts = $this->layoutOptions();
        $data['layout'] = $this->detailData['layout'] ?? array_key_first($availableLayouts);

        if ($hasPageFeatures) {
            $defaultLang = $this->getDefaultLanguageCode();

            $this->resetValidation();
            $hasErrors = false;

            if (empty($this->detailData['name'][$defaultLang] ?? '')) {
                $this->addError('detailData.name', __('validation.required', ['attribute' => __('Title')]));
                $hasErrors = true;
            }

            if (empty($this->detailData['slug'][$defaultLang] ?? '')) {
                $this->addError('detailData.slug', __('validation.required', ['attribute' => __('URL')]));
                $hasErrors = true;
            }

            if ($hasErrors) {
                return;
            }

            $nameData = [];
            if (isset($this->detailData['name']) && is_array($this->detailData['name'])) {
                foreach ($this->detailData['name'] as $lang => $nameValue) {
                    if (! empty($nameValue)) {
                        $nameData[$lang] = $nameValue;
                    }
                }
            }

            $slugData = [];
            if (isset($this->detailData['slug']) && is_array($this->detailData['slug'])) {
                foreach ($this->detailData['slug'] as $lang => $slug) {
                    if (! empty($slug)) {
                        $slugData[$lang] = $slug;
                    } elseif (! empty($nameData[$lang] ?? '')) {
                        $slugData[$lang] = $this->ensureUniqueSlug($this->generateSlug($nameData[$lang], $lang), $lang);
                    }
                }
            }

            $data['name'] = $nameData;
            $data['slug'] = $slugData;
            $data['is_active'] = true;
        } else {
            $data['name'] = null;
            $data['slug'] = null;
            $data['is_active'] = true;
        }

        $modelId = $this->modelId ?: null;
        if ($modelId) {
            $page = Page::updateOrCreate(['id' => $modelId], $data);
        } else {
            $page = Page::create($data);
            $this->modelId = $page->id;
        }

        $this->storeProcess($page);

        $this->dispatch('storeElements');
    }

    private function initializeEmptySlugArray(): array
    {
        $languages = $this->getActiveTenantLanguageCodes();
        if (empty($languages)) {
            $languages = [$this->getDefaultLanguageCode()];
        }

        return array_fill_keys($languages, '');
    }

    private function urlWithoutDomain(Media $media): string
    {
        $url = Storage::disk($media->disk)->url($media->path);

        return mb_strstr($url, '/storage');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">

    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ $collectionLayout['title'] ?? __('Page') }}

            <div class="ml-auto">
                <div class="flex items-center gap-4">
                    @if($this->pageModel?->id && $this->hasPageFeatures)
                        <button
                            x-data
                            x-init="if (!Alpine.store('elements')) Alpine.store('elements', { collapsed: false })"
                            @click="$store.elements.collapsed = !$store.elements.collapsed"
                            class="px-3 py-1.5 rounded-md text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors"
                            x-text="$store.elements.collapsed ? '{{ __('Elemente aufklappen') }}' : '{{ __('Elemente zuklappen') }}'"
                        ></button>
                    @endif

                    <livewire:cms::language-switcher/>

                    @if($this->pageModel?->id && $this->hasPageFeatures)
                        @php $pageUrl = $this->getPageUrl(); @endphp
                        @if($pageUrl)
                            <a href="{{ $pageUrl }}" target="_blank"
                               class="px-4 py-2 rounded-md text-sm font-medium bg-gray-900 text-white hover:bg-gray-800 transition-colors">
                                {{ __('Zur Seite') }}
                            </a>
                        @endif
                    @endif
                </div>
            </div>

        </x-noerd::modal-title>
    </x-slot:header>
    <div>
        @if($collectionLayout)
            <!-- Sort Field for Collections -->
            <div class="flex">
                <div class="flex ml-auto items-center my-6 space-x-4">
                    <div class="flex ml-auto items-center space-x-2">
                        <label for="sort" class="text-sm text-gray-600 font-medium">Sort:</label>
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

            <!-- Collection Fields (Blue Box) -->
            <div class="p-4 border border-blue-200 mb-4 relative overflow-hidden rounded-lg bg-blue-50 after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-blue-950/5 bg-[image:radial-gradient(var(--pattern-fg)_1px,_transparent_0)] bg-[size:10px_10px] bg-fixed [--pattern-fg:var(--color-blue-950)]/5">
                @include('noerd::components.detail.block', array_merge($collectionLayout, ['model' => $detailData]))
            </div>
        @endif

        @php
            $effectiveLayout = $this->hasPageFeatures ? $pageLayout : array_merge($pageLayout, ['tabs' => []]);
        @endphp
        <x-noerd::tab-content :layout="$effectiveLayout" :showBlock="$this->hasPageFeatures" :model="$detailData">
            <x-slot:tab1>
                @include('cms::components._page-elements', ['hasPageFeatures' => $this->hasPageFeatures])
            </x-slot:tab1>
        </x-noerd::tab-content>
    </div>

    <x-slot:footer>
        @if($modelId)
            <x-noerd::button variant="secondary" wire:click="copy" wire:confirm="{{ __('Seite kopieren?') }}">
                {{ __('Copy') }}
            </x-noerd::button>
        @endif
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>

</x-noerd::page>
