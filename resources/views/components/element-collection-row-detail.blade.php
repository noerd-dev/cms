<?php

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\CollectionEntryStore;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Traits\HandlesImageFields;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Helpers\AccessHelper;
use Noerd\Support\LayoutFields;
use Noerd\Traits\NoerdDetail;

/*
 | The editor of one row of an element collection. It has no route — the
 | modal stack mounts it with what the browser names — so it asks for the CMS
 | app and the page permissions itself, in mount() AND in every action. The
 | collection is resolved on the server from the locked key (an element
 | collection of the current tenant whose owner still exists), its row fields
 | come from the collection's stored schema, and only a row OF THIS collection
 | is ever loaded, stored, copied or deleted.
 */
new class extends Component {
    use HandlesImageFields;
    use LanguageFilterTrait;
    use NoerdDetail;
    use WithFileUploads;

    public $detailModel = Page::class;

    /**
     * Override the trait's URL-bound modelId with a dedicated alias. This editor
     * is opened as a modal nested inside the entry editor (which already binds
     * ?pageId), so a shared URL param would clobber the id on first open. The
     * dedicated ?entryId param deep-links the open row without that conflict.
     */
    public ?string $detailPrimary = 'entryId';

    #[Locked]
    public ?string $collectionKey = null;

    /** The row schema rendered by the form — a copy of the collection's stored schema. */
    #[Locked]
    public ?array $collectionLayout = null;

    private ?Collection $resolvedCollection = null;

    private bool $collectionResolved = false;

    public function mount(?string $collectionKey = null): void
    {
        abort_unless($this->mayUseCms() && $this->canReadObject(), 403);

        $this->initDetail();

        $this->collectionKey = $collectionKey ?? $this->collectionKey;
        $elementCollection = $this->elementCollection();
        $row = $this->modelId && $elementCollection ? $this->row() : null;

        // An unknown collection, or a row that is not one of its rows, opens
        // nothing — the editor stays inert.
        if (! $elementCollection || ($this->modelId && ! $row)) {
            $this->collectionKey = null;
            $this->collectionLayout = null;
            $this->modelId = null;
            $this->detailData = [];

            return;
        }

        $this->collectionLayout = $this->rowSchema();

        $data = is_array($row?->data) ? $row->data : [];

        $this->detailData = $this->initializeDetailData($data);
        $this->detailData['sort'] = $row->sort ?? 0;
    }

    public function hydrate(): void
    {
        $this->collectionLayout = $this->rowSchema();
    }

    #[On('languageChanged')]
    public function onLanguageChanged(): void
    {
        // The roundtrip re-renders the translatable inputs against the new language.
    }

    public function store(): void
    {
        $elementCollection = $this->elementCollection();

        if (! $elementCollection || ! $this->mayUseCms() || ! $this->canSaveObject()) {
            return;
        }

        $rules = [];
        $this->extractRulesFromFields($this->rowSchema()['fields'] ?? [], $rules);
        if ($rules !== []) {
            $this->validate($rules);
        }

        $page = app(CollectionEntryStore::class)->persistRow(
            $elementCollection,
            $this->rowSchema(),
            $this->detailData,
            $this->modelId ? (int) $this->modelId : null,
        );

        if (! $page instanceof Page) {
            return;
        }

        $this->finishStore($page);
        $this->dispatch('refreshList-cms::collection-entries-list');
        $this->dispatch('refreshList-element-collection-field');
    }

    public function copy(): void
    {
        // copy() is not covered by the generic store/delete guard.
        if (! $this->mayUseCms() || ! $this->canCreateObject()) {
            return;
        }

        $sourceRow = $this->row();
        if (! $sourceRow) {
            return;
        }

        $newRow = app(CollectionEntryStore::class)->duplicateRow($sourceRow);

        $this->modelId = $newRow->id;
        $this->detailData['sort'] = $newRow->sort;

        $this->dispatch('refreshList-cms::collection-entries-list');
        $this->dispatch('refreshList-element-collection-field');
        $this->showSuccessIndicator = true;
    }

    public function delete(): void
    {
        if (! $this->mayUseCms() || ! $this->canDeleteObject()) {
            return;
        }

        $this->row()?->delete();

        $this->dispatch('refreshList-element-collection-field');
        $this->closeModalProcess('cms::collection-entries-list');
    }

    /**
     * The inputs this form renders outside its layout: the sort position and
     * the row fields of the collection's stored schema. The client may only
     * change what is declared.
     *
     * @return list<string>
     */
    protected function additionalDetailFields(): array
    {
        $fields = ['detailData.sort'];

        LayoutFields::walk($this->rowSchema()['fields'] ?? [], function (array $field) use (&$fields): void {
            if (is_string($field['name'] ?? null)) {
                $fields[] = $field['name'];
            }
        });

        return $fields;
    }

    /**
     * @return list<string>
     */
    protected function imageFieldNames(): array
    {
        return $this->imageFieldNamesOf($this->rowSchema()['fields'] ?? []);
    }

    private function mayUseCms(): bool
    {
        return AccessHelper::canUseApp('CMS');
    }

    /**
     * The element collection the locked key names: one of the current tenant
     * (the tenant scope) whose owner — the collection entry or the page element
     * — still exists in it. Resolved once per request.
     */
    private function elementCollection(): ?Collection
    {
        if ($this->collectionResolved) {
            return $this->resolvedCollection;
        }

        $this->collectionResolved = true;

        if ($this->collectionKey === null || $this->collectionKey === '') {
            return $this->resolvedCollection = null;
        }

        $collection = Collection::query()
            ->where('collection_key', mb_strtoupper($this->collectionKey))
            ->where('is_element_collection', true)
            ->first();

        return $this->resolvedCollection = $collection && $this->ownerExists($collection) ? $collection : null;
    }

    private function ownerExists(Collection $collection): bool
    {
        if ($collection->page_id) {
            return Page::query()->whereKey($collection->page_id)->exists();
        }

        if ($collection->element_page_id) {
            $element = ContentElement::query()->find($collection->element_page_id);

            return $element !== null && $element->content()->exists();
        }

        return false;
    }

    /**
     * The row being edited — only ever a row of the element collection.
     */
    private function row(): ?Page
    {
        $elementCollection = $this->elementCollection();

        if (! $elementCollection || ! $this->modelId) {
            return null;
        }

        return Page::query()->where('collection_id', $elementCollection->id)->find($this->modelId);
    }

    /**
     * The row schema of the collection, from its stored definition.
     *
     * @return array<string, mixed>|null
     */
    private function rowSchema(): ?array
    {
        $elementCollection = $this->elementCollection();

        return $elementCollection ? app(ElementCollectionService::class)->schemaFor($elementCollection) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function initializeDetailData(array $data): array
    {
        $languages = $this->activeLanguageCodes();

        foreach ($this->rowSchema()['fields'] ?? [] as $field) {
            $key = str_replace('detailData.', '', $field['name'] ?? '');
            if ($key === '') {
                continue;
            }

            if (str_starts_with($field['type'] ?? '', 'translatable')) {
                $existing = is_array($data[$key] ?? null) ? $data[$key] : [];
                foreach ($languages as $language) {
                    $existing[$language] = is_string($existing[$language] ?? null) ? $existing[$language] : '';
                }
                $data[$key] = $existing;
            } else {
                $data[$key] ??= '';
            }
        }

        return $data;
    }

}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ $collectionLayout['title'] ?? __('Entry') }}

            <div class="ml-auto">
                <livewire:cms::language-switcher/>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="$modelId" :showBlock="false">
        <x-slot:tab1>
    <div class="py-4">
        <div class="flex">
            <div class="flex ml-auto items-center mb-6 space-x-4">
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

        @include('noerd::components.detail.block', array_merge($collectionLayout ?? ['fields' => []], ['model' => $detailData, 'modelId' => $modelId]))
    </div>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        @if($modelId)
            <x-noerd::button variant="secondary" wire:click="copy" wire:confirm="{{ __('Copy this entry?') }}">
                {{ __('Copy') }}
            </x-noerd::button>
        @endif
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
