<?php

use Livewire\Component;
use Noerd\Cms\Models\Redirect;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use NoerdList;

    public $listModel = Redirect::class;

    public ?string $detailRoute = 'cms.redirect.detail';

    public $detailComponent = 'cms::redirect-detail';

    public function listData(): array
    {
        $rows = $this->listQuery($this->listModel)
            ->with('targetPage')
            ->paginate($this->perPage);

        foreach ($rows->items() as $row) {
            $row->target_page_name = RelationFieldDefinition::normalizeDisplayValue($row->targetPage?->name) ?: '';
        }

        return $this->buildList($rows);
    }
}; ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
