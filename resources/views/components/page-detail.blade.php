<?php

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\CollectionEntryStore;
use Noerd\Cms\Services\PageCopyService;
use Noerd\Cms\Services\PageSlugService;
use Noerd\Cms\Support\PageLayouts;
use Noerd\Cms\Traits\EditsContentDocument;
use Noerd\Cms\Traits\HandlesImageFields;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\LayoutFields;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Traits\NoerdDetail;

/*
 | The page editor: a plain page or a collection entry (when a collectionKey is
 | given) with the element builder below the form. Slugs, collection entries
 | and element mutations are delegated to their services; this component only
 | owns the form state and the editor chrome.
 |
 | The collection is SERVER state: an existing page edits the collection it
 | belongs to (whatever the opener passed), and its layout is resolved from the
 | definition on every request — never from a client copy. Every handler that
 | writes a field accepts only a field the server-side layout declares with the
 | matching type.
 */
new class extends Component {
    use EditsContentDocument;
    use HandlesImageFields;
    use LanguageFilterTrait;
    use NoerdDetail;
    use WithFileUploads;

    public ?string $detailPrimary = 'pageId';

    public $detailModel = Page::class;

    /** The key of the collection the edited entry belongs to (null: a plain page). */
    #[Locked]
    public ?string $collectionKey = null;

    /** The collection definition's layout, rendered above the form — a copy of collectionFields(). */
    #[Locked]
    public ?array $collectionLayout = null;

    /** @var array<string, mixed>|null */
    private ?array $resolvedCollectionLayout = null;

    private bool $collectionLayoutResolved = false;

    #[Computed]
    public function hasPageFeatures(): bool
    {
        $layout = $this->collectionFields();

        if (! $this->collectionKey || ! $layout) {
            return true;
        }

        return $layout['hasPage'] ?? true;
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

        return Page::with('content.elements')->find($this->modelId);
    }

    public function contentDocumentType(): string
    {
        return Page::contentType();
    }

    public function editableContent(): ?Content
    {
        return $this->pageModel?->ownContent();
    }

    #[Computed]
    public function elements()
    {
        return FieldHelper::getAllElements();
    }

    public function mount(?string $collectionKey = null): void
    {
        $this->initDetail();

        $page = $this->modelId ? Page::find($this->modelId) : null;

        // An existing page edits the collection it belongs to; only a NEW
        // entry takes the collection the opener names.
        $this->collectionKey = $page instanceof Page
            ? $this->collectionKeyOf($page, $collectionKey)
            : $this->newEntryCollectionKey($collectionKey);

        $this->collectionLayout = $this->collectionFields();

        $page ??= new Page();

        $this->mountPageDetail($page);
        $this->mergeCollectionData($page);

        // Populate relationTitles for saved page relations defined in the collection layout
        foreach ($this->pageRelationFieldNames() as $fieldName) {
            $relatedPage = ! empty($this->detailData[$fieldName] ?? null) ? Page::find($this->detailData[$fieldName]) : null;
            if ($relatedPage) {
                $this->relationTitles[$fieldName] = RelationFieldDefinition::normalizeDisplayValue($relatedPage->name);
            }
        }
    }

    /**
     * The rendered copy of the layout follows the server on every request.
     */
    public function hydrate(): void
    {
        $this->collectionLayout = $this->collectionFields();
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

    /**
     * The page picker's answer — only for a page relation field of the
     * collection layout. The context names the field, so it is client input.
     */
    #[On('pageSelected')]
    public function pageSelected($value, $context): void
    {
        $fieldName = str_replace('detailData.', '', (string) $context);

        if (! $this->canSaveObject() || ! in_array($fieldName, $this->pageRelationFieldNames(), true)) {
            return;
        }

        $page = is_numeric($value) ? Page::find((int) $value) : null;
        if (! $page) {
            return;
        }

        $this->relationTitles[$fieldName] = RelationFieldDefinition::normalizeDisplayValue($page->name);
        $this->detailData[$fieldName] = $page->id;
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

        $this->validatePageForm();

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

        // Only what the form declares — never content_id, collection_id, the
        // id or the tenant from the client state.
        $data = $this->writableDetailData(Page::class);
        $data['layout'] = ! empty($data['layout']) ? $data['layout'] : PageLayouts::default();
        // Normalized, unique across every language of the tenant (the website
        // resolves a slug in any of them); violations were refused above.
        $data['slug'] = app(PageSlugService::class)->prepare(
            is_array($this->detailData['slug'] ?? null) ? $this->detailData['slug'] : [],
            (int) TenantHelper::currentTenantId(),
            $this->modelId ? (int) $this->modelId : null,
        )['slugs'];
        unset($data['content_id'], $data['collection_id'], $data['data']);

        if ($this->modelId) {
            $page = Page::find($this->modelId);

            // An id the tenant scope cannot resolve stores nothing — never a new page.
            if (! $page instanceof Page) {
                return;
            }

            $page->fill($data)->save();
        } else {
            $page = Page::create($data);
        }

        $this->persistContentDocument($page->ownContent(), $page);

        $this->finishStore($page);
    }

    public function copy(): void
    {
        if (! $this->canSaveObject() || ! $this->pageModel) {
            return;
        }

        $newPage = app(PageCopyService::class)->copy($this->pageModel);

        $this->modelId = $newPage->id;
        $this->mountPageDetail($newPage);
        $this->mergeCollectionData($newPage);
        unset($this->pageModel);
        $this->reloadContentDocument();

        $this->dispatch('listRefresh');
        $this->dispatch('refreshList-cms::pages-list');
        $this->showSuccessIndicator = true;
    }

    /**
     * The inputs this form renders outside its layout: the sort position and
     * the collection fields (resolved on the server from the collection
     * definition). The client may only change what is declared.
     *
     * @return list<string>
     */
    protected function additionalDetailFields(): array
    {
        $fields = ['detailData.sort'];

        LayoutFields::walk($this->collectionFields()['fields'] ?? [], function (array $field) use (&$fields): void {
            if (is_string($field['name'] ?? null)) {
                $fields[] = $field['name'];
            }
        });

        return $fields;
    }

    /**
     * The image fields of the page form and of the collection layout.
     *
     * @return list<string>
     */
    protected function imageFieldNames(): array
    {
        return array_values(array_unique([
            ...$this->imageFieldNamesOf($this->pageLayout['fields'] ?? []),
            ...$this->imageFieldNamesOf($this->collectionFields()['fields'] ?? []),
        ]));
    }

    protected function mountPageDetail(?Page $page = null): void
    {
        $page ??= new Page();

        $this->detailData = $page->toArray();
        $this->detailData['custom_attributes'] ??= [];

        if (empty($this->detailData['layout'])) {
            $this->detailData['layout'] = PageLayouts::default();
        }

        $activeLangCodes = $this->activeLanguageCodes();

        foreach (['name', 'slug', 'meta_title', 'meta_description'] as $field) {
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
    }

    /**
     * The layout of the edited collection, resolved from its definition once
     * per request — null for a plain page.
     *
     * @return array<string, mixed>|null
     */
    private function collectionFields(): ?array
    {
        if (! $this->collectionLayoutResolved) {
            $this->resolvedCollectionLayout = $this->collectionKey ? CollectionHelper::getCollectionFields($this->collectionKey) : null;
            $this->collectionLayoutResolved = true;
        }

        return $this->resolvedCollectionLayout;
    }

    /**
     * The collection key of an existing page: its own collection's. A key the
     * opener passed is kept only when it names that same collection (a
     * hyphenated URL key of an underscored definition key).
     */
    private function collectionKeyOf(Page $page, ?string $requested): ?string
    {
        $collection = $page->collection_id ? Collection::query()->find($page->collection_id) : null;

        if (! $collection instanceof Collection) {
            return null;
        }

        $collectionKey = mb_strtoupper((string) $collection->collection_key);

        if ($requested !== null && $requested !== '' && $this->definitionKey($requested) === $collectionKey) {
            return $requested;
        }

        return mb_strtolower($collectionKey);
    }

    /**
     * A new entry may only be created in a collection the tenant defines (not
     * in an element collection, whose rows have an editor of their own).
     */
    private function newEntryCollectionKey(?string $requested): ?string
    {
        if ($requested === null || $requested === '' || CollectionHelper::getCollectionFields($requested) === null) {
            return null;
        }

        $isElementCollection = Collection::query()
            ->where('collection_key', mb_strtoupper($requested))
            ->where('is_element_collection', true)
            ->exists();

        return $isElementCollection ? null : $requested;
    }

    private function definitionKey(string $collectionKey): string
    {
        $definition = app(CollectionDefinitionRepositoryContract::class)->find($collectionKey);

        return mb_strtoupper((string) ($definition?->key ?: $collectionKey));
    }

    /**
     * The detailData keys of the page relation fields of the collection
     * layout — the only keys `pageSelected` may write.
     *
     * @return list<string>
     */
    private function pageRelationFieldNames(): array
    {
        $names = [];

        LayoutFields::walk($this->collectionFields()['fields'] ?? [], function (array $field) use (&$names): void {
            $type = $field['type'] ?? null;
            $isPageRelation = $type === 'pageRelation'
                || ($type === 'relation' && in_array($field['modalComponent'] ?? null, ['pages-list', 'cms::pages-list'], true));
            $name = str_replace('detailData.', '', (string) ($field['name'] ?? ''));

            if ($isPageRelation && $name !== '' && ! str_contains($name, '.')) {
                $names[] = $name;
            }
        });

        return $names;
    }

    /**
     * The rules of the page form (validateFromLayout()), of the collection
     * fields and of the layout picklist: one of the discovered layouts, or the
     * one the page already has.
     */
    private function validatePageForm(): void
    {
        $this->validateFromLayout();

        $rules = [];
        $this->extractRulesFromFields($this->collectionFields()['fields'] ?? [], $rules);

        $layouts = array_keys(PageLayouts::options());
        $storedLayout = $this->pageModel?->getRawOriginal('layout');
        if (is_string($storedLayout) && $storedLayout !== '') {
            $layouts[] = $storedLayout;
        }

        $rules['detailData.layout'] = ['nullable', 'string', Rule::in(array_values(array_unique($layouts)))];

        $this->validate($rules);
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

        foreach ($page->data as $key => $value) {
            if (! str_starts_with($key, 'detailData.') && ! in_array($key, ['name', 'slug', 'layout', 'sort'], true)) {
                $this->detailData[$key] = $value;
            }
        }

        $this->detailData['sort'] ??= $page->sort ?? 0;
    }

    private function storeCollectionPage(): void
    {
        $store = app(CollectionEntryStore::class);
        $layout = $this->collectionFields();

        if ($layout['hasPage'] ?? true) {
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
            $layout,
            $this->detailData,
            $this->modelId ? (int) $this->modelId : null,
            (int) TenantHelper::currentTenantId(),
            auth()->id(),
            $this->defaultLanguageCode(),
        );

        if (! $page instanceof Page) {
            return;
        }

        if ($this->hasPageFeatures) {
            $this->persistContentDocument($page->ownContent(), $page);
        }

        $this->finishStore($page);
    }

} ?>

@assets
    <x-cms::editor-assets />
@endassets

<x-noerd::page>

    <x-slot:header>
        <x-noerd::modal-title>
            {{ $collectionLayout['title'] ?? __('Page') }}

            <x-slot:actions>
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
                @if ($this->hasPageFeatures)
                    <div class="mt-6">
                        <x-cms::content-document :config="$this->contentDocumentConfig()" />
                    </div>
                @endif
            </x-slot:tab1>
        </x-noerd::tab-content>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>

</x-noerd::page>
