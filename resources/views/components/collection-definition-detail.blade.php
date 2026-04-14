<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\CollectionDefinitionData;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;

    #[Url(as: 'collectionDefinitionId', keep: false, except: '')]
    public $modelId = null;

    public array $fields = [];

    public bool $isEditing = false;

    public array $originalFieldNames = [];

    public bool $showRenameConfirmation = false;

    public array $pendingRenames = [];

    public function mount(): void
    {
        $this->initDetail();
        $this->pageLayout = StaticConfigHelper::getComponentFields('collection-definition-detail');

        $repository = app(CollectionDefinitionRepositoryContract::class);

        $this->detailData = [
            'filename' => '',
            'title' => '',
            'titleList' => '',
            'description' => '',
            'hasPage' => false,
        ];

        if ($this->modelId) {
            $this->isEditing = true;

            $definition = $repository->find($this->modelId);

            if ($definition) {
                $this->detailData['filename'] = $definition->filename;
                $this->detailData['title'] = $definition->title;
                $this->detailData['titleList'] = $definition->titleList;
                $this->detailData['description'] = $definition->description ?? '';
                $this->detailData['hasPage'] = $definition->hasPage;

                $this->fields = $definition->fields;
                foreach ($this->fields as $index => $field) {
                    $this->originalFieldNames[$index] = $field['name'];
                }
            }
        }
    }

    public function addField(): void
    {
        $this->fields[] = [
            'name' => '',
            'label' => '',
            'type' => 'text',
            'colspan' => 6,
        ];
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function store(): void
    {
        // Normalize filename: lowercase, strip .yml extension, replace underscores with hyphens
        $this->detailData['filename'] = mb_strtolower($this->detailData['filename']);
        $this->detailData['filename'] = preg_replace('/\.ya?ml$/i', '', $this->detailData['filename']);
        $this->detailData['filename'] = str_replace('_', '-', $this->detailData['filename']);

        $rules = [
            'detailData.filename' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/'],
            'detailData.title' => ['required', 'string', 'max:255'],
            'detailData.titleList' => ['required', 'string', 'max:255'],
        ];

        foreach ($this->fields as $index => $field) {
            $rules["fields.{$index}.name"] = ['required', 'string', 'max:255'];
            $rules["fields.{$index}.label"] = ['required', 'string', 'max:255'];
            $rules["fields.{$index}.type"] = ['required', 'string'];
        }

        $this->validate($rules);

        $repository = app(CollectionDefinitionRepositoryContract::class);
        $filename = $this->detailData['filename'];

        // Prevent duplicate filenames (when creating or renaming)
        $isRenaming = $this->isEditing && $filename !== $this->modelId;
        if ((! $this->isEditing || $isRenaming) && $repository->exists($filename)) {
            $this->addError('detailData.filename', __('A file with this name already exists.'));

            return;
        }

        // Detect renamed fields
        $renames = [];
        if ($this->isEditing) {
            foreach ($this->originalFieldNames as $index => $oldName) {
                if (isset($this->fields[$index]) && $this->fields[$index]['name'] !== $oldName && $oldName !== '') {
                    $renames[$oldName] = $this->fields[$index]['name'];
                }
            }
        }

        // If there are renames and user hasn't confirmed yet, ask
        if ($renames && ! $this->showRenameConfirmation) {
            $this->pendingRenames = $renames;
            $this->showRenameConfirmation = true;

            return;
        }

        $key = mb_strtoupper(str_replace('-', '_', $filename));
        $data = new CollectionDefinitionData(
            filename: $filename,
            key: $key,
            title: $this->detailData['title'],
            titleList: $this->detailData['titleList'],
            description: $this->detailData['description'] ?: null,
            hasPage: (bool) $this->detailData['hasPage'],
            fields: array_values($this->fields),
        );

        $repository->save(
            $data,
            originalFilename: $this->isEditing ? $this->modelId : null,
        );

        // Update collections table collection_key on rename (per-tenant scope)
        if ($isRenaming) {
            Collection::where('tenant_id', Auth::user()->selected_tenant_id)
                ->where('collection_key', mb_strtoupper(str_replace('-', '_', $this->modelId)))
                ->update(['collection_key' => $key]);
        }

        // Ensure collection DB record exists with created_by
        if (! $this->isEditing) {
            Collection::firstOrCreate([
                'tenant_id' => Auth::user()->selected_tenant_id,
                'collection_key' => $key,
            ], [
                'name' => $this->detailData['titleList'],
                'created_by' => Auth::id(),
            ]);
        }

        $this->isEditing = true;
        $this->modelId = $filename;

        $this->dispatch('listRefresh');
        $this->showSuccessIndicator = true;
    }

    public function confirmRenameAndSave(): void
    {
        $this->renameFieldsInDatabase();
        $this->showRenameConfirmation = false;
        $this->syncOriginalFieldNames();
        $this->store();
    }

    public function skipRenameAndSave(): void
    {
        $this->pendingRenames = [];
        $this->showRenameConfirmation = false;
        $this->syncOriginalFieldNames();
        $this->store();
    }

    private function syncOriginalFieldNames(): void
    {
        $this->originalFieldNames = [];
        foreach ($this->fields as $index => $field) {
            $this->originalFieldNames[$index] = $field['name'];
        }
    }

    private function renameFieldsInDatabase(): void
    {
        $collectionKey = mb_strtoupper(str_replace('-', '_', $this->modelId));
        $collection = Collection::where('tenant_id', Auth::user()->selected_tenant_id)
            ->where('collection_key', $collectionKey)
            ->first();

        if (! $collection) {
            return;
        }

        $pages = Page::where('collection_id', $collection->id)
            ->whereNotNull('data')
            ->get();

        foreach ($pages as $page) {
            $data = $page->data;
            $changed = false;

            foreach ($this->pendingRenames as $oldKey => $newKey) {
                if (array_key_exists($oldKey, $data) && ! array_key_exists($newKey, $data)) {
                    $data[$newKey] = $data[$oldKey];
                    unset($data[$oldKey]);
                    $changed = true;
                }
            }

            if ($changed) {
                $page->data = $data;
                $page->saveQuietly();
            }
        }

        $this->pendingRenames = [];
    }

    public function copy(): void
    {
        if (! $this->modelId) {
            return;
        }

        $repository = app(CollectionDefinitionRepositoryContract::class);

        try {
            $newFilename = $repository->copy($this->modelId);
        } catch (\RuntimeException $e) {
            $this->addError('detailData.filename', __('A file with this name already exists.'));

            return;
        }

        // Mirror the new definition into the collections instance table so it
        // shows up with the correct creator in lists.
        $newDefinition = $repository->find($newFilename);
        if ($newDefinition) {
            Collection::firstOrCreate([
                'tenant_id' => Auth::user()->selected_tenant_id,
                'collection_key' => $newDefinition->key,
            ], [
                'name' => $newDefinition->titleList,
                'created_by' => Auth::id(),
            ]);
        }

        $this->dispatch('listRefresh');
        $this->closeModalProcess('collection-definitions-list');
    }

    public function delete(): void
    {
        if (! $this->modelId) {
            return;
        }

        $repository = app(CollectionDefinitionRepositoryContract::class);

        $collectionKey = mb_strtoupper(str_replace('-', '_', $this->modelId));
        Collection::where('tenant_id', Auth::user()->selected_tenant_id)
            ->where('collection_key', $collectionKey)
            ->delete();

        $repository->delete($this->modelId);

        $this->closeModalProcess('collection-definitions-list');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('Collection Definition') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <div class="px-6 py-4">
        <h3 class="text-sm font-medium text-gray-700 mb-3">{{ __('Fields') }}</h3>

        @if(count($fields) === 0)
            <p class="text-sm text-gray-500 italic">{{ __('No fields defined yet.') }}</p>
        @else
            <table class="min-w-full border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th class="border-r first:pl-6 border-b border-gray-300 bg-brand-navi/75 py-3.5 pr-3 pl-2 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter">
                            {{ __('Field Name') }}
                        </th>
                        <th class="border-r border-b border-gray-300 bg-brand-navi/75 py-3.5 pr-3 pl-2 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter">
                            {{ __('Label') }}
                        </th>
                        <th class="border-r border-b border-gray-300 bg-brand-navi/75 py-3.5 pr-3 pl-2 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter" style="width: 200px;">
                            {{ __('Type') }}
                        </th>
                        <th class="border-r border-b border-gray-300 bg-brand-navi/75 py-3.5 pr-3 pl-2 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter" style="width: 80px;">
                            {{ __('Width') }}
                        </th>
                        <th class="last:border-r-0 border-b border-gray-300 bg-brand-navi/75 py-3.5 pr-3 pl-2 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter" style="width: 50px;">
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fields as $index => $field)
                        <tr wire:key="field-{{ $index }}" class="group hover:bg-brand-bg border border-black/10">
                            <td class="py-1 first:pl-4 border-gray-300 border-r border-b">
                                <input type="text" wire:model="fields.{{ $index }}.name"
                                       placeholder="{{ __('Field Name') }}"
                                       class="border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5">
                                @error("fields.{$index}.name") <span class="text-red-500 text-xs px-1.5">{{ $message }}</span> @enderror
                            </td>
                            <td class="py-1 border-gray-300 border-r border-b">
                                <input type="text" wire:model="fields.{{ $index }}.label"
                                       placeholder="{{ __('Label') }}"
                                       class="border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5">
                                @error("fields.{$index}.label") <span class="text-red-500 text-xs px-1.5">{{ $message }}</span> @enderror
                            </td>
                            <td class="py-1 border-gray-300 border-r border-b">
                                <select wire:model="fields.{{ $index }}.type"
                                        class="border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5">
                                    <option value="text">Text</option>
                                    <option value="translatableText">Translatable Text</option>
                                    <option value="translatableTextarea">Translatable Textarea</option>
                                    <option value="translatableRichText">Translatable RichText</option>
                                    <option value="image">Image</option>
                                    <option value="email">E-Mail</option>
                                    <option value="tel">Tel</option>
                                    <option value="checkbox">Checkbox</option>
                                </select>
                            </td>
                            <td class="py-1 border-gray-300 border-r border-b">
                                <select wire:model="fields.{{ $index }}.colspan"
                                        class="border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5">
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="6">6</option>
                                    <option value="12">12</option>
                                </select>
                            </td>
                            <td class="py-1 last:border-r-0 border-gray-300 border-b text-center">
                                <button type="button" wire:click="removeField({{ $index }})"
                                        class="text-red-500 hover:text-red-700 p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <button type="button" wire:click="addField"
                class="mt-3 inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            {{ __('Add Field') }}
        </button>
    </div>

    @if($showRenameConfirmation)
        <div class="fixed inset-0 z-50 flex items-center justify-center" x-data x-on:keydown.escape.window="$wire.skipRenameAndSave()">
            <div class="fixed inset-0 bg-gray-800/50" wire:click="skipRenameAndSave"></div>
            <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full mx-4 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('Rename fields in entries?') }}</h3>
                <p class="text-sm text-gray-600 mb-4">{{ __('The following fields were renamed. Should the corresponding data in all existing entries of this collection be updated as well?') }}</p>
                <ul class="text-sm text-gray-700 mb-4 space-y-1">
                    @foreach($pendingRenames as $oldName => $newName)
                        <li class="flex items-center gap-2">
                            <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $oldName }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $newName }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="skipRenameAndSave"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('No, save definition only') }}
                    </button>
                    <button type="button" wire:click="confirmRenameAndSave"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        {{ __('Yes, update entries') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <x-slot:footer>
        <div class="flex items-center w-full gap-2">
            @if($isEditing)
                <div class="flex gap-2 mr-auto">
                    <x-noerd::button variant="secondary" wire:click="copy" wire:confirm="{{ __('Only the collection structure will be copied, not the entries. Continue?') }}">
                        {{ __('Copy') }}
                    </x-noerd::button>
                </div>
            @endif
            @php
                $entryCount = 0;
                if ($isEditing && $modelId) {
                    $collectionKey = mb_strtoupper(str_replace('-', '_', $modelId));
                    $collection = Collection::where('tenant_id', Auth::user()->selected_tenant_id)
                        ->where('collection_key', $collectionKey)
                        ->first();
                    if ($collection) {
                        $entryCount = $collection->rows()->count();
                    }
                }
            @endphp
            <x-noerd::delete-save-bar :showDelete="$isEditing" deleteMessage="{{ __('Warning: The collection and all associated entries (:count entries) will be permanently deleted. Continue?', ['count' => $entryCount]) }}" />
        </div>
    </x-slot:footer>
</x-noerd::page>
