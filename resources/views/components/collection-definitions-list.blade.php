<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Noerd\Traits\NoerdList;
use Symfony\Component\Yaml\Yaml;

new class extends Component
{
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'collection-definition-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with(): array
    {
        $collectionsPath = base_path('app-configs/cms/collections');
        $files = glob($collectionsPath . '/*.yml');

        $items = [];
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);

            try {
                $content = Yaml::parseFile($file);
            } catch (\Exception $e) {
                continue;
            }

            $item = [
                'id' => $filename,
                'titleList' => $content['titleList'] ?? $filename,
                'key' => $content['key'] ?? mb_strtoupper(str_replace('-', '_', $filename)),
                'hasPage' => ! empty($content['hasPage']) ? '✓' : '–',
                'fieldCount' => isset($content['fields']) ? count($content['fields']) : 0,
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
                ],
            ]),
        ];
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
