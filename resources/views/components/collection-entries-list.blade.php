<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdList;

    public string|int|null $collectionKey = null;

    public ?array $collectionLayout = null;

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
            $this->collectionKey = request()->get('key');
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
        Noerd::modal('cms::page-detail', ['modelId' => $modelId, 'collectionKey' => $this->collectionKey, 'relations' => $relations]);
    }

    public function with(): array
    {
        if (! $this->collectionKey) {
            return [
                'listConfig' => $this->buildList(collect([]), [
                    'title' => 'Collections',
                    'actions' => [['label' => 'Neuer Eintrag', 'action' => 'listAction']],
                    'disableSearch' => false,
                    'columns' => [],
                ]),
            ];
        }

        // Get or create the parent collection. Pull the display name from the
        // definition repository when available so it matches what the user
        // configured (instead of falling back to ucfirst on the key).
        $definition = app(CollectionDefinitionRepositoryContract::class)->find($this->collectionKey);
        $parentCollection = Collection::firstOrCreate([
            'tenant_id' => auth()->user()->selected_tenant_id,
            'collection_key' => mb_strtoupper($this->collectionKey),
        ], [
            'name' => $definition?->titleList ?: ucfirst($this->collectionKey),
            'created_by' => auth()->id(),
        ]);

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

                // Search in dynamic fields from YAML configuration
                if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                    foreach ($this->collectionLayout['fields'] as $field) {
                        $fieldName = $field['name'] ?? '';
                        // Remove 'detailData.' prefix
                        $fieldKey = str_replace('detailData.', '', $fieldName);

                        // Skip image fields for search
                        if (($field['type'] ?? '') === 'image') {
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

        // Transform data for display
        $rows->getCollection()->transform(function ($page) use ($selectedLanguage) {
            $data = is_array($page->data) ? $page->data : [];
            $transformedData = [
                'id' => $page->id,
                'sort' => $page->sort ?? 0,
                'updated_at' => $page->updated_at->format('d.m.Y H:i'),
            ];

            // Add dynamic fields from YAML configuration
            if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                foreach ($this->collectionLayout['fields'] as $field) {
                    $fieldName = $field['name'] ?? '';
                    // Remove 'detailData.' prefix
                    $fieldKey = str_replace('detailData.', '', $fieldName);

                    $value = '';
                    if (isset($data[$fieldKey])) {
                        $fieldData = $data[$fieldKey];

                        // Handle translatable fields
                        if (is_array($fieldData)) {
                            $value = $fieldData[$selectedLanguage] ?? array_values($fieldData)[0] ?? '';
                        } else {
                            $value = $fieldData;
                        }
                    }

                    // Handle special field types
                    if (($field['type'] ?? '') === 'image' && $value) {
                        $value = '✓ Bild vorhanden';
                    }

                    $transformedData[$fieldKey] = $value ?: '-';
                }
            }

            return $transformedData;
        });

        $collectionTitle = $this->collectionLayout['title'] ?? ucfirst($this->collectionKey);
        $actionLabel = __('New Entry');

        // Generate dynamic columns from YAML fields
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

                $columns[] = [
                    'field' => $fieldKey,
                    'label' => $label,
                    'width' => $width,
                ];
            }
        }

        // Add standard columns
        $columns[] = ['field' => 'sort', 'label' => 'Sortierung', 'width' => 0.5];
        $columns[] = ['field' => 'updated_at', 'label' => __('Last Modified')];

        return [
            'listConfig' => $this->buildList($rows, [
                'title' => $collectionTitle,
                'actions' => [
                    ['label' => 'Manage Collection', 'action' => 'manageCollection', 'style' => 'secondary', 'shortcut' => 'c'],
                    ['label' => $actionLabel, 'action' => 'listAction', 'shortcut' => 'n'],
                ],
                'disableSearch' => false,
                'columns' => $columns,
            ]),
        ];
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

<x-noerd::page :disableModal="$disableModal">
    @if($collectionKey)
        <x-noerd::list />
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">{{ __('Please select a collection from the navigation.') }}</p>
        </div>
    @endif
</x-noerd::page>
