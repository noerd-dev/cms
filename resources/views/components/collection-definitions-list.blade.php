<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Support\CollectionDefinitionData;
use Noerd\Helpers\TenantHelper;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use NoerdList;

    public ?string $detailRoute = 'cms.collection-definition.detail';

    public $detailComponent = 'cms::collection-definition-detail';

    /**
     * Repository-backed rows (no $listModel) — the object permission that guards
     * reading and bulk-deleting this list is declared explicitly.
     */
    public ?string $objectPermissionModel = CollectionDefinition::class;

    public function mount(): void
    {
        $this->mountList();
    }

    #[Computed]
    public function tableFilters(): array
    {
        return [
            [
                'label' => __('Type'),
                'column' => 'has_page',
                'type' => 'Picklist',
                'options' => [
                    '' => __('All types'),
                    'page' => __('With page'),
                    'data' => __('Data only'),
                ],
            ],
        ];
    }

    public function with(): array
    {
        $repository = app(CollectionDefinitionRepositoryContract::class);

        $collectionMeta = DB::table('cms_collections')
            ->leftJoin('cms_pages', 'cms_pages.collection_id', '=', 'cms_collections.id')
            ->leftJoin('noerd_users', 'cms_collections.created_by', '=', 'noerd_users.id')
            ->where('cms_collections.tenant_id', TenantHelper::currentTenantId())
            ->select(
                'cms_collections.collection_key',
                DB::raw('count(cms_pages.id) as entry_count'),
                'noerd_users.name as creator_name',
            )
            ->groupBy('cms_collections.collection_key', 'noerd_users.name')
            ->get()
            ->keyBy('collection_key');

        $entryCounts = $collectionMeta->pluck('entry_count', 'collection_key')->toArray();
        $creatorNames = $collectionMeta->pluck('creator_name', 'collection_key')->toArray();

        $items = [];
        foreach ($repository->all() as $definition) {
            /** @var CollectionDefinitionData $definition */
            $item = [
                'id' => $definition->filename,
                'titleList' => $definition->titleList,
                'key' => $definition->key,
                'hasPage' => $definition->hasPage ? '✓' : '–',
                'fieldCount' => count($definition->fields),
                'entryCount' => (int) ($entryCounts[$definition->key] ?? 0),
                'createdBy' => $creatorNames[$definition->key] ?? 'System',
            ];

            // Apply search filter
            if (! empty($this->search)) {
                $searchLower = mb_strtolower($this->search);
                $matchesSearch = mb_strpos(mb_strtolower($item['titleList']), $searchLower) !== false
                    || mb_strpos(mb_strtolower($item['key']), $searchLower) !== false
                    || mb_strpos(mb_strtolower($definition->filename), $searchLower) !== false;

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
                'title' => 'Collection Definitions',
                'actions' => [['label' => 'New Collection', 'action' => 'listAction']],
                'disableSearch' => false,
                'notSortableColumns' => ['titleList', 'key', 'hasPage', 'fieldCount', 'entryCount', 'createdBy'],
                'columns' => [
                    ['field' => 'titleList', 'label' => 'Title (Plural)'],
                    ['field' => 'key', 'label' => 'Key'],
                    ['field' => 'hasPage', 'label' => 'Has Page'],
                    ['field' => 'fieldCount', 'label' => 'Fields'],
                    ['field' => 'entryCount', 'label' => 'Entries'],
                    ['field' => 'createdBy', 'label' => 'Created by'],
                ],
            ]),
        ];
    }
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
