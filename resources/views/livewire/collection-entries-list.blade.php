<?php

use Livewire\Volt\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;

new class () extends Component {
    use Noerd;

    public const COMPONENT = 'collection-entries-list';

    public ?string $collectionKey = null;
    public ?array $collectionLayout = null;

    public function mount(): void
    {
        $this->collectionKey = request()->get('key');

        if (!$this->collectionKey) {
            // Redirect to collection-files if no key is provided
            $this->redirect(route('cms.collection-files'));
            return;
        }

        // Load collection layout
        $this->collectionLayout = CollectionHelper::getCollectionFields($this->collectionKey);

        if (request()->create) {
            $this->tableAction();
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'page-detail',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'collectionKey' => $this->collectionKey, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        if (!$this->collectionKey) {
            return [
                'rows' => collect([]),
                'tableConfig' => [
                    'title' => 'Collections',
                    'newLabel' => 'Neuer Eintrag',
                    'disableSearch' => false,
                    'columns' => [],
                ],
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
        if (!empty($this->search)) {
            $query->where(function ($q): void {
                // Search in standard fields
                $q->whereRaw('JSON_EXTRACT(name, "$.de") LIKE ?', ['%' . $this->search . '%'])
                    ->orWhereRaw('JSON_EXTRACT(name, "$.en") LIKE ?', ['%' . $this->search . '%']);

                // Search in dynamic fields from YAML configuration
                if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                    foreach ($this->collectionLayout['fields'] as $field) {
                        $fieldName = $field['name'] ?? '';
                        $fieldKey = str_replace('model.', '', $fieldName);

                        // Skip image fields for search
                        if (($field['type'] ?? '') === 'image') {
                            continue;
                        }

                        // Search in translatable fields
                        $q->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}.de\") LIKE ?", ['%' . $this->search . '%'])
                            ->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}.en\") LIKE ?", ['%' . $this->search . '%'])
                            ->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}\") LIKE ?", ['%' . $this->search . '%']);
                    }
                }
            });
        }

        $rows = $query->paginate(self::PAGINATION);

        // Transform data for display
        $rows->getCollection()->transform(function ($page) {
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
                    $fieldKey = str_replace('model.', '', $fieldName); // Remove 'model.' prefix

                    $value = '';
                    if (isset($data[$fieldKey])) {
                        $fieldData = $data[$fieldKey];

                        // Handle translatable fields
                        if (is_array($fieldData)) {
                            $value = $fieldData['de'] ?? $fieldData['en'] ?? '';
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
                $fieldKey = str_replace('model.', '', $fieldName); // Remove 'model.' prefix
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
            'rows' => $rows,
            'tableConfig' => [
                'title' => $collectionTitle,
                'newLabel' => $newLabel,
                'disableSearch' => false,
                'columns' => $columns,
            ],
        ];
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    @if($collectionKey)
        @include('noerd::components.table.table-build', [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
            'component' => self::COMPONENT
        ])
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">Bitte wählen Sie eine Collection aus der Navigation.</p>
        </div>
    @endif
</x-noerd::page>