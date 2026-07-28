<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Facades\Noerd;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdList;

    public $listModel = Page::class;

    public string|int|null $collectionKey = null;

    /**
     * Numeric id of the backing collection row, mirrored to the URL so the
     * open overlay can be deep-linked (?collection=8). Kept in sync in with().
     */
    #[Url(as: 'collection', keep: false, except: '')]
    public string|int|null $collectionId = null;

    public ?array $collectionLayout = null;

    /**
     * When true, this list edits an element collection bound to a single
     * entry+field. The collection-definition management action is hidden.
     */
    public bool $elementCollection = false;

    #[Computed]
    public function tableFilters(): array
    {
        if (! $this->hasMultipleLanguages()) {
            return [];
        }

        return [$this->getLanguageListFilter()];
    }

    public function storeActiveListFilters(): void
    {
        session(['listFilters' => $this->listFilters]);

        if (! empty($this->listFilters['language'])) {
            session(['selectedLanguage' => $this->listFilters['language']]);
        }
    }

    /**
     * Resolve collection key from ID or string
     */
    protected function resolveCollectionKey(string|int|null $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // If it's a numeric string or integer, treat as ID
        if (is_numeric($input)) {
            $collection = Collection::where('tenant_id', auth()->user()->selected_tenant_id)
                ->where('id', (int) $input)
                ->first();

            return $collection?->collection_key ? strtolower($collection->collection_key) : null;
        }

        // If it's a string, return as-is
        return (string) $input;
    }

    public function mount(): void
    {
        if (! $this->collectionKey) {
            $this->collectionKey = $this->collectionId ?? request()->get('key');
        }

        // Resolve collection key (supports both string and ID)
        $this->collectionKey = $this->resolveCollectionKey($this->collectionKey);

        // Load collection layout
        $this->collectionLayout = CollectionHelper::getCollectionFields($this->collectionKey);

        if (request()->create) {
            $this->listAction();
        }
    }

    #[On('listRefresh')]
    public function reloadCollectionLayout(): void
    {
        $this->collectionLayout = CollectionHelper::getCollectionFields($this->collectionKey);
    }

    public function manageCollection(): void
    {
        Noerd::modal('cms::collection-definition-detail', ['modelId' => $this->collectionKey]);
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        // Element collections use a dedicated row editor without the ?pageId URL
        // binding, so opening a row inside the (already modal) entry editor does not
        // clobber its id on first open.
        if ($this->elementCollection) {
            Noerd::modal('cms::element-collection-row-detail', ['modelId' => $modelId, 'collectionKey' => $this->collectionKey]);

            return;
        }

        Noerd::modal('cms::page-detail', ['modelId' => $modelId, 'collectionKey' => $this->collectionKey, 'relations' => $relations]);
    }

    public function listData(): array
    {
        if (! $this->collectionKey) {
            return $this->buildList(collect([]), [
                'title' => 'Collections',
                'actions' => [['label' => 'Neuer Eintrag', 'action' => 'listAction']],
                'disableSearch' => false,
                'columns' => [],
            ]);
        }

        // Get or create the parent collection. Pull the display name from the
        // definition repository when available so it matches what the user
        // configured (instead of falling back to ucfirst on the key).
        // The definition's key is authoritative: URL keys are filenames
        // (hyphenated), while definition keys may use underscores — uppercasing
        // the filename would create an empty duplicate row next to the real one.
        $definition = app(CollectionDefinitionRepositoryContract::class)->find($this->collectionKey);
        $parentCollection = Collection::firstOrCreate([
            'tenant_id' => auth()->user()->selected_tenant_id,
            'collection_key' => $definition?->key ?: mb_strtoupper($this->collectionKey),
        ], [
            'name' => $definition?->titleList ?: ucfirst($this->collectionKey),
            'created_by' => auth()->id(),
        ]);

        $this->collectionId = $parentCollection->id;

        // Get collection entries (pages)
        $query = Page::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('collection_id', $parentCollection->id)
            ->orderBy('sort', 'asc')
            ->orderBy('created_at', 'desc');

        // Apply search if provided
        if (! empty($this->search)) {
            $query->where(function ($q): void {
                // Search in standard fields
                $q->whereRaw('JSON_EXTRACT(name, "$.de") LIKE ?', ['%'.$this->search.'%'])
                    ->orWhereRaw('JSON_EXTRACT(name, "$.en") LIKE ?', ['%'.$this->search.'%']);

                // Search in dynamic fields from the collection definition
                if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                    foreach ($this->collectionLayout['fields'] as $field) {
                        $fieldName = $field['name'] ?? '';
                        // Remove 'detailData.' prefix
                        $fieldKey = str_replace('detailData.', '', $fieldName);

                        // Skip non-text fields for search
                        if (in_array($field['type'] ?? '', ['image', 'element-collection'], true)) {
                            continue;
                        }

                        // Search in translatable fields
                        $q->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}.de\") LIKE ?", ['%'.$this->search.'%'])
                            ->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}.en\") LIKE ?", ['%'.$this->search.'%'])
                            ->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}\") LIKE ?", ['%'.$this->search.'%']);
                    }
                }
            });
        }

        $rows = $query->paginate($this->perPage);

        $selectedLanguage = $this->listFilters['language']
            ?? session('selectedLanguage')
            ?? $this->getDefaultLanguageCode();

        // Resolve linked page names for pageRelation fields in one query.
        $pageRelationKeys = collect($this->collectionLayout['fields'] ?? [])
            ->filter(fn ($field) => ($field['type'] ?? '') === 'pageRelation')
            ->map(fn ($field) => str_replace('detailData.', '', $field['name'] ?? ''))
            ->filter()
            ->values();

        $linkedPageNames = [];
        if ($pageRelationKeys->isNotEmpty()) {
            $linkedPageIds = $rows->getCollection()
                ->flatMap(fn ($page) => $pageRelationKeys->map(fn ($key) => data_get($page->data, $key)))
                ->filter(fn ($id) => is_numeric($id))
                ->unique();
            $linkedPageNames = Page::whereIn('id', $linkedPageIds)->pluck('name', 'id')
                ->map(fn ($name) => RelationFieldDefinition::normalizeDisplayValue($name))
                ->all();
        }

        // Transform data for display
        $rows->getCollection()->transform(function ($page) use ($selectedLanguage, $linkedPageNames) {
            $data = is_array($page->data) ? $page->data : [];
            $transformedData = [
                'id' => $page->id,
                'sort' => $page->sort ?? 0,
                'updated_at' => $page->updated_at->format('d.m.Y H:i'),
            ];

            // Add dynamic fields from the collection definition
            if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                foreach ($this->collectionLayout['fields'] as $field) {
                    $fieldName = $field['name'] ?? '';
                    // Remove 'detailData.' prefix
                    $fieldKey = str_replace('detailData.', '', $fieldName);
                    $fieldType = $field['type'] ?? '';

                    $value = '';
                    if ($fieldType === 'element-collection') {
                        // Element-collection data lives in a separate hidden collection
                        // owned by this entry; show its row count.
                        $elementCollection = Collection::query()
                            ->where('collection_key', app(ElementCollectionService::class)->keyFor(ElementCollectionService::OWNER_PAGE, $page->id, $fieldKey))
                            ->where('is_element_collection', true)
                            ->first();
                        $count = $elementCollection ? $elementCollection->rows()->count() : 0;
                        $value = $count > 0 ? $count.' '.trans_choice('Eintrag|Einträge', $count) : '';
                    } elseif (isset($data[$fieldKey])) {
                        $fieldData = $data[$fieldKey];

                        if (is_array($fieldData)) {
                            // Translatable field: pick selected language, then any string value
                            $translated = $fieldData[$selectedLanguage] ?? null;
                            if (! is_string($translated)) {
                                $translated = null;
                                foreach ($fieldData as $candidate) {
                                    if (is_string($candidate)) {
                                        $translated = $candidate;
                                        break;
                                    }
                                }
                            }
                            $value = $translated ?? '';
                        } else {
                            $value = $fieldData;
                        }
                    }

                    // Handle special field types
                    if ($fieldType === 'image' && $value) {
                        $value = '✓ Bild vorhanden';
                    }

                    if ($fieldType === 'pageRelation') {
                        // Empty string keeps the badge cell empty instead of rendering a "-" pill.
                        $transformedData[$fieldKey] = is_numeric($value) ? ($linkedPageNames[(int) $value] ?? '') : '';

                        continue;
                    }

                    if (! is_scalar($value)) {
                        $value = '';
                    }

                    $transformedData[$fieldKey] = $value !== '' && $value !== null ? $value : '-';
                }
            }

            return $transformedData;
        });

        $collectionTitle = $this->collectionLayout['title'] ?? ucfirst($this->collectionKey);
        $actionLabel = __('New Entry');

        // Element collections are not user-defined, so the "Manage Collection"
        // (definition) action is suppressed; only the "New Entry" action remains.
        $actions = [];
        if (! $this->elementCollection) {
            $actions[] = ['label' => 'Manage Collection', 'action' => 'manageCollection', 'style' => 'secondary', 'shortcut' => 'c'];
        }
        $actions[] = ['label' => $actionLabel, 'action' => 'listAction', 'shortcut' => 'n'];

        // Generate dynamic columns from the collection definition fields
        $columns = [];
        if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
            foreach ($this->collectionLayout['fields'] as $field) {
                $fieldName = $field['name'] ?? '';
                // Remove 'detailData.' prefix
                $fieldKey = str_replace('detailData.', '', $fieldName);
                $label = $field['label'] ?? ucfirst($fieldKey);

                // Calculate width weight based on field type
                $width = match ($field['type'] ?? 'text') {
                    'image' => 0.8,
                    'translatableText' => 1.2,
                    'translatableTextarea' => 1.5,
                    default => 1,
                };

                $column = [
                    'field' => $fieldKey,
                    'label' => $label,
                    'width' => $width,
                ];

                if (($field['type'] ?? '') === 'pageRelation') {
                    $column['type'] = 'badge';
                }

                $columns[] = $column;
            }
        }

        // Add standard columns
        $columns[] = ['field' => 'sort', 'label' => 'Sortierung', 'width' => 0.5];
        $columns[] = ['field' => 'updated_at', 'label' => __('Last Modified')];

        return $this->buildList($rows, [
            'title' => $collectionTitle,
            'actions' => $actions,
            'disableSearch' => false,
            'columns' => $columns,
        ]);
    }

    public function rendering(): void
    {
        $this->loadListFilters();

        $selectedLanguage = session('selectedLanguage');
        if ($selectedLanguage && empty($this->listFilters['language'])) {
            $this->listFilters['language'] = $selectedLanguage;
        }

        if (empty($this->listFilters['language']) && empty(session('selectedLanguage'))) {
            $defaultCode = $this->getDefaultLanguageCode();
            $this->listFilters['language'] = $defaultCode;
            session(['selectedLanguage' => $defaultCode]);
        }
    }

    private function getDefaultLanguageCode(): string
    {
        $defaultLanguage = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('is_default', true)
            ->first();

        return $defaultLanguage?->code ?? 'de';
    }
} ?>

<x-noerd::page>
    @if($collectionKey)
        <x-noerd::list />
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">{{ __('Please select a collection from the navigation.') }}</p>
        </div>
    @endif
</x-noerd::page>
