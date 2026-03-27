<?php

use Livewire\Component;
use Noerd\Cms\Models\Author;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'author-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with()
    {
        $rows = $this->listQuery(Author::class)->paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        $this->loadListFilters();

        if ((int) request()->authorId) {
            $this->listAction(request()->authorId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
