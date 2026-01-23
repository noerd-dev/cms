<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Noerd\Traits\Noerd;

new class extends Component
{
    use LanguageFilterTrait;
    use Noerd;

    public const COMPONENT = 'collection-entries-list';

    protected const ALLOWED_TABLE_FILTERS = ['language'];

    public string|int|null $collectionKey = null;

    public ?array $collectionLayout = null;

    #[Computed]
    public function tableFilters(): array
    {
        if (! $this->hasMultipleLanguages()) {
            return [];
        }

        return [$this->getLanguageFilter()];
    }

    public function storeActiveListFilters(): void
    {
        session(['activeListFilters' => $this->activeListFilters]);

        if (! empty($this->activeListFilters['language'])) {
            session(['selectedLanguage' => $this->activeListFilters['language']]);
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

    public function listAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'page-detail',
            source: self::COMPONENT,
            arguments: ['pageId' => $modelId, 'collectionKey' => $this->collectionKey, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        if (! $this->collectionKey) {
            return [
                'listConfig' => $this->buildList(collect([]), [
                    'title' => 'Collections',
                    'newLabel' => 'Neuer Eintrag',
                    'disableSearch' => false,
                    'columns' => [],
                ]),
            ];
        }

        // Get or create the parent collection
        $parentCollection = Collection::firstOrCreate([
            'tenant_id' => auth()->user()->selected_tenant_id,
            'collection_key' => mb_strtoupper($this->collectionKey),
        ], [
            'name' => ucfirst($this->collectionKey),
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
                        // Remove 'model.' or 'pageData.' prefix
                        $fieldKey = preg_replace('/^(model\.|pageData\.)/', '', $fieldName);

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

        $rows = $query->paginate(self::PAGINATION);

        $selectedLanguage = $this->activeListFilters['language']
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
                    // Remove 'model.' or 'pageData.' prefix
                    $fieldKey = preg_replace('/^(model\.|pageData\.)/', '', $fieldName);

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
        $newLabel = $this->collectionLayout['buttonList'] ?? 'Neuer Eintrag';

        // Generate dynamic columns from YAML fields
        $columns = [];
        if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
            foreach ($this->collectionLayout['fields'] as $field) {
                $fieldName = $field['name'] ?? '';
                // Remove 'model.' or 'pageData.' prefix
                $fieldKey = preg_replace('/^(model\.|pageData\.)/', '', $fieldName);
                $label = $field['label'] ?? ucfirst($fieldKey);

                // Calculate width based on field type and position
                $width = match ($field['type'] ?? 'text') {
                    'image' => 15,
                    'translatableText' => 25,
                    'translatableTextarea' => 30,
                    default => 20,
                };

                $columns[] = [
                    'field' => $fieldKey,
                    'label' => $label,
                    'width' => $width,
                ];
            }
        }

        // Add standard columns
        $columns[] = ['field' => 'sort', 'label' => 'Sortierung', 'width' => 10];
        $columns[] = ['field' => 'updated_at', 'label' => __('Last Modified'), 'width' => 15];

        return [
            'listConfig' => $this->buildList($rows, [
                'title' => $collectionTitle,
                'newLabel' => $newLabel,
                'disableSearch' => false,
                'columns' => $columns,
            ]),
        ];
    }

    public function rendering(): void
    {
        $this->loadActiveListFilters();

        $selectedLanguage = session('selectedLanguage');
        if ($selectedLanguage && empty($this->activeListFilters['language'])) {
            $this->activeListFilters['language'] = $selectedLanguage;
        }

        if (empty($this->activeListFilters['language']) && empty(session('selectedLanguage'))) {
            $defaultCode = $this->getDefaultLanguageCode();
            $this->activeListFilters['language'] = $defaultCode;
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
            <p class="text-gray-500">{{ __('cms_please_select_collection') }}</p>
        </div>
    @endif
</x-noerd::page>
