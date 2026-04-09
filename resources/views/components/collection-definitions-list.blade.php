<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Traits\NoerdList;
use Symfony\Component\Yaml\Yaml;

new class extends Component
{
    use NoerdList;

    public function mount(): void
    {
        $this->listId = Str::random();
        $this->loadListFilters();
    }

    #[Computed]
    public function tableFilters(): array
    {
        return [
            [
                'label' => __('cms_label_type'),
                'column' => 'has_page',
                'type' => 'Picklist',
                'options' => [
                    '' => __('cms_all_types'),
                    'page' => __('cms_with_page'),
                    'data' => __('cms_data_only'),
                ],
            ],
        ];
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'collection-definition-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    /**
     * Remove YAML files for collections that no longer exist in the database.
     */
    private function removeOrphanedCollectionYamlFiles(string $collectionsPath): void
    {
        $dbKeys = DB::table('collections')
            ->pluck('collection_key')
            ->toArray();

        foreach (glob($collectionsPath . '/*.yml') as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $collectionKey = mb_strtoupper(str_replace('-', '_', $filename));

            if (! in_array($collectionKey, $dbKeys)) {
                unlink($file);
            }
        }
    }

    /**
     * Restore missing YAML files for collections that have entries in the database.
     */
    private function restoreMissingCollectionYamlFiles(string $collectionsPath): void
    {
        $existingKeys = collect(glob($collectionsPath . '/*.yml'))
            ->map(function ($file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);

                return mb_strtoupper(str_replace('-', '_', $filename));
            })
            ->toArray();

        $missingCollections = DB::table('collections')
            ->whereNotIn('collection_key', $existingKeys)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pages')
                    ->whereColumn('pages.collection_id', 'collections.id');
            })
            ->get(['collection_key', 'name']);

        foreach ($missingCollections as $collection) {
            $filename = str_replace('_', '-', mb_strtolower($collection->collection_key));
            $path = $collectionsPath . '/' . $filename . '.yml';

            $dataKeys = DB::table('pages')
                ->join('collections', 'pages.collection_id', '=', 'collections.id')
                ->where('collections.collection_key', $collection->collection_key)
                ->whereNotNull('pages.data')
                ->value('pages.data');

            $fields = [];
            if ($dataKeys) {
                $decoded = json_decode($dataKeys, true);
                if (is_array($decoded)) {
                    foreach (array_keys($decoded) as $key) {
                        $fields[] = [
                            'name' => 'detailData.' . $key,
                            'label' => ucfirst($key),
                            'type' => 'text',
                            'colspan' => 12,
                        ];
                    }
                }
            }

            $data = [
                'title' => $collection->name ?? ucfirst($filename),
                'titleList' => $collection->name ?? ucfirst($filename),
                'key' => $collection->collection_key,
                'description' => '',
                'hasPage' => false,
                'fields' => $fields,
            ];

            file_put_contents($path, Yaml::dump($data, 4, 2));
        }
    }

    public function with(): array
    {
        $collectionsPath = base_path('app-configs/cms/collections');

        $this->removeOrphanedCollectionYamlFiles($collectionsPath);
        $this->restoreMissingCollectionYamlFiles($collectionsPath);

        $collectionMeta = DB::table('collections')
            ->leftJoin('pages', 'pages.collection_id', '=', 'collections.id')
            ->leftJoin('noerd_users', 'collections.created_by', '=', 'noerd_users.id')
            ->select(
                'collections.collection_key',
                DB::raw('count(pages.id) as entry_count'),
                'noerd_users.name as creator_name',
            )
            ->groupBy('collections.collection_key', 'noerd_users.name')
            ->get()
            ->keyBy('collection_key');

        $entryCounts = $collectionMeta->pluck('entry_count', 'collection_key')->toArray();
        $creatorNames = $collectionMeta->pluck('creator_name', 'collection_key')->toArray();

        $files = glob($collectionsPath . '/*.yml');

        $items = [];
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);

            try {
                $content = Yaml::parseFile($file);
            } catch (\Exception $e) {
                continue;
            }

            $collectionKey = mb_strtoupper(str_replace('-', '_', $filename));

            $item = [
                'id' => $filename,
                'titleList' => $content['titleList'] ?? $filename,
                'key' => $content['key'] ?? $collectionKey,
                'hasPage' => ! empty($content['hasPage']) ? '✓' : '–',
                'fieldCount' => isset($content['fields']) ? count($content['fields']) : 0,
                'entryCount' => $entryCounts[$collectionKey] ?? 0,
                'createdBy' => $creatorNames[$collectionKey] ?? 'System',
            ];

            // Apply search filter
            if (! empty($this->search)) {
                $searchLower = mb_strtolower($this->search);
                $matchesSearch = mb_strpos(mb_strtolower($item['titleList']), $searchLower) !== false
                    || mb_strpos(mb_strtolower($item['key']), $searchLower) !== false
                    || mb_strpos(mb_strtolower($filename), $searchLower) !== false;

                if (! $matchesSearch) {
                    continue;
                }
            }

            // Apply type filter
            $hasPageFilter = $this->listFilters['has_page'] ?? '';
            if ($hasPageFilter === 'page' && $item['hasPage'] !== '✓') {
                continue;
            }
            if ($hasPageFilter === 'data' && $item['hasPage'] !== '–') {
                continue;
            }

            $items[] = $item;
        }

        // Sort by titleList
        usort($items, fn ($a, $b) => strcasecmp($a['titleList'], $b['titleList']));

        $page = $this->getPage();
        $perPage = $this->perPage;
        $collection = collect($items);
        $rows = new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
        );

        return [
            'listConfig' => $this->buildList($rows, [
                'title' => 'cms_label_collection_definitions',
                'actions' => [['label' => 'cms_label_new_collection_definition', 'action' => 'listAction']],
                'disableSearch' => false,
                'columns' => [
                    ['field' => 'titleList', 'label' => __('cms_label_title_plural')],
                    ['field' => 'key', 'label' => 'Key'],
                    ['field' => 'hasPage', 'label' => __('cms_label_has_page')],
                    ['field' => 'fieldCount', 'label' => __('cms_label_field_count')],
                    ['field' => 'entryCount', 'label' => __('cms_label_entry_count')],
                    ['field' => 'createdBy', 'label' => __('cms_label_created_by')],
                ],
            ]),
        ];
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
