<?php

use Illuminate\Support\Facades\File;
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\Noerd;

new class () extends Component {
    use Noerd;

    public const COMPONENT = 'collections-list';

    public function mount(): void
    {
        if (request()->create) {
            $this->tableAction();
        }

        if (request()->file) {
            $this->editFile(request()->file);
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'collection-detail',
            source: self::COMPONENT,
            arguments: ['fileName' => $modelId, 'relationId' => $relationId],
        );
    }

    public function editFile($fileName): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'collection-detail.blade.php',
            source: self::COMPONENT,
            arguments: ['fileName' => $fileName],
        );
    }

    public function deleteFile($fileName): void
    {
        $filePath = base_path('app-configs/cms/collections/' . $fileName);

        if (File::exists($filePath)) {
            File::delete($filePath);
            $this->dispatch('noerd-notification', [
                'type' => 'success',
                'message' => __('Collection file was successfully deleted.'),
            ]);
        }
    }

    public function with(): array
    {
        $collectionsPath = base_path('app-configs/cms/collections');
        $files = [];

        if (File::exists($collectionsPath)) {
            $yamlFiles = File::files($collectionsPath);

            foreach ($yamlFiles as $file) {
                if ($file->getExtension() === 'yml') {
                    $fileName = $file->getFilename();
                    $lastModified = File::lastModified($file->getPathname());

                    if (empty($this->search) || str_contains(mb_strtolower($fileName), mb_strtolower($this->search))) {
                        $files[] = [
                            'id' => $fileName,
                            'name' => str_replace('.yml', '', $fileName),
                            'file_name' => $fileName,
                            'last_modified' => date('d.m.Y H:i', $lastModified),
                            'size' => $this->formatBytes($file->getSize()),
                        ];
                    }
                }
            }
        }

        // Sort by name
        usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));

        // Convert to paginated collection
        $collection = collect($files);
        $perPage = self::PAGINATION;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $collection->slice($offset, $perPage)->values();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $collection->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );

        return [
            'rows' => $paginated,
            'tableConfig' => [
                'title' => 'Collections',
                'newLabel' => 'cms_new_collection',
                'disableSearch' => false,
                'columns' => [
                    ['field' => 'name', 'label' => 'Name', 'width' => 30],
                    ['field' => 'file_name', 'label' => 'cms_filename', 'width' => 25],
                    ['field' => 'last_modified', 'label' => __('Last Modified'), 'width' => 20],
                    ['field' => 'size', 'label' => 'cms_size', 'width' => 15],
                ],
            ],
        ];
    }

    private function formatBytes($size, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])
</x-noerd::page>
