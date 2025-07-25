<?php

use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Noerd\Traits\NoerdTableTrait;

new class extends Component {

    use NoerdTableTrait;

    public const COMPONENT = 'collections-table';

    public $tableLayout;
    public $pageLayout;

    #[Url]
    public ?string $key = null;

    public function mount()
    {
        if (!$this->key) {
            abort(404);
        }

        $this->tableLayout = CollectionHelper::getCollectionTable($this->key);
        $this->pageLayout = CollectionHelper::getCollectionFields($this->key);

        if ((int)request()->customerId) {
            $this->tableAction(request()->customerId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch('set-app-id', ['id' => null]);

        $this->dispatch(
            event: 'noerdModal',
            component: 'collection-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $collectionRows = Collection::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('collection_key', strtoupper($this->key))
            ->paginate(self::PAGINATION);

        $rows = [];
        foreach ($collectionRows as $collectionRow) {
            $row = json_decode($collectionRow->data, true);
            $row['id'] = $collectionRow->id;

            $rows[] = $row;
        }

        $arrayRows = [];
        foreach ($rows as $row) {
            // NAME
            if (isset($row['name'])) {
                $oldName = $row['name'];
                $row['name'] = $row['name'][session('selectedLanguage')] ?? $row['name'];

                if (strlen($row['name']) == 0) {
                    $row['name'] = $oldName;
                }
            }

            // DESCRIPTION
            if (isset($row['description'])) {
                $oldName = $row['description'];
                $row['description'] = $row['description'][session('selectedLanguage')] ?? $row['description'];

                if (strlen($row['description']) == 0) {
                    $row['description'] = $oldName;
                }
            }

            // ADD to row
            $arrayRows[] = $row;
        }


        return [
            'rows' => $arrayRows,
        ];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build',
      [
          'title' => $pageLayout['titleList'],
          'description' => '',
          'newLabel' => $pageLayout['buttonList'],
          'redirectAction' => '',
          'disableSearch' => true,
          'table' => $tableLayout,
      ])
</x-noerd::page>
