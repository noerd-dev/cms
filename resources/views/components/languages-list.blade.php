<?php

use Livewire\Component;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('cms::language-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        $rows = $this->listQuery(CmsLanguage::class)->paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }

    public function rendering()
    {
        if ((int) request()->cmsLanguageId) {
            $this->listAction(request()->cmsLanguageId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
