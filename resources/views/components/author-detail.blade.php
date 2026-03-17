<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Models\Author;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;

    #[Url(as: 'authorId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = Author::class;

    public array $authorData = [];

    public function mount(): void
    {
        $this->initDetail();

        $author = new Author;
        if ($this->modelId) {
            $author = Author::find($this->modelId) ?? new Author;
        }

        $this->authorData = $author->toArray();
    }

    public function store(): void
    {
        $this->validate([
            'authorData.name' => ['required', 'string', 'max:255'],
        ]);

        $data = $this->authorData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;

        $author = Author::updateOrCreate(
            ['id' => $this->modelId],
            $data,
        );

        $this->storeProcess($author);
    }

    public function delete(): void
    {
        $author = Author::find($this->modelId);
        $author->delete();
        $this->closeModalProcess($this->getListComponent());
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)" />
    </x-slot:footer>
</x-noerd::page>
