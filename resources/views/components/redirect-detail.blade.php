<?php

use Illuminate\Validation\Rule;
use Livewire\Component;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Models\Redirect;
use Noerd\Helpers\TenantHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'redirectId';

    public $detailModel = Redirect::class;

    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        $tenantId = TenantHelper::currentTenantId();

        $this->validate([
            'detailData.source_path' => ['required', 'string', 'max:191'],
            'detailData.target_page_id' => [
                'required',
                'integer',
                Rule::exists('cms_pages', 'id')->where('tenant_id', $tenantId),
            ],
            'detailData.is_active' => ['nullable', 'boolean'],
        ]);

        $path = Redirect::normalizePath((string) $this->detailData['source_path']);
        $this->detailData['source_path'] = $path;

        if ($path === '/') {
            $this->addError('detailData.source_path', __('The start page cannot be redirected.'));

            return;
        }

        $duplicate = Redirect::where('tenant_id', $tenantId)
            ->where('source_path', $path)
            ->when($this->modelId, fn($query) => $query->where('id', '!=', $this->modelId))
            ->exists();

        if ($duplicate) {
            $this->addError('detailData.source_path', __('This path is already redirected.'));

            return;
        }

        $targetPage = Page::find($this->detailData['target_page_id']);
        $targetSlugs = array_map(
            fn($slug): string => Redirect::normalizePath((string) $slug),
            array_filter((array) ($targetPage?->slug ?? []), 'is_scalar'),
        );

        if (in_array($path, $targetSlugs, true)) {
            $this->addError('detailData.source_path', __('The path already belongs to the selected page.'));

            return;
        }

        $data = collect($this->detailData)
            ->except(['created_at', 'updated_at'])
            ->toArray();
        $data['tenant_id'] = $tenantId;
        $data['target_page_id'] = (int) $data['target_page_id'];
        $data['is_active'] = ! empty($data['is_active']);

        $redirect = Redirect::updateOrCreate(['id' => $this->modelId], $data);

        $this->storeProcess($redirect);
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('Redirect') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId)"/>
    </x-slot:footer>
</x-noerd::page>
