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

    public $detailModel = Author::class;

    public function store(): void
    {
        $this->validate([
            'detailData.name' => ['required', 'string', 'max:255'],
        ]);

        $data = $this->detailData;
        $data['tenant_id'] = auth()->user()->selected_tenant_id;

        $author = Author::updateOrCreate(
            ['id' => $this->modelId],
            $data,
        );

        $this->storeProcess($author);
    }
} ?>

<x-noerd::page>
    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)" />
    </x-slot:footer>
</x-noerd::page>
