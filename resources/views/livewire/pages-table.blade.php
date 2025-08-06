<?php

use Livewire\Volt\Component;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'pages-table';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {


        $this->dispatch(
            event: 'noerdModal',
            component: 'page-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with()
    {
        $rows = Page::where('tenant_id', Auth::user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->whereJsonDoesntContain('name->de', 'CollectionPage')
            ->paginate(self::PAGINATION);

        // TODO : This is a workaround for the multilingual names.
        foreach ($rows as $row) {
            $oldName = $row->name;
            $row->name = json_decode($row->name, true);
            $row->name = $row->name[session('selectedLanguage')] ?? $row->name;

            if (strlen($row->name) == 0) {
                $row->name = $oldName;
            }
        }

        return [
            'rows' => $rows,
        ];
    }

    public function rendering()
    {
        if ((int)request()->orderConfrimationId) {
            $this->tableAction(request()->orderConfrimationId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <div>
        @include('noerd::components.table.table-build',
        [
            'title' => __('Seite'),
            'newLabel' => __('Neue Seite'),
            'redirectAction' => '',
            'disableSearch' => false,
            'table' => [
                [
                    'width' => 30,
                    'field' => 'name',
                    'label' => __('Name'),
                ],
            ],
        ])
    </div>
</x-noerd::page>
