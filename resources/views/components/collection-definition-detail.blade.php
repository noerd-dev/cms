<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\CollectionDefinitionData;
use Noerd\Facades\Noerd;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;

    public ?string $detailPrimary = 'collectionDefinitionId';

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

                $this->fields = array_map(
                    fn (array $field): array => $field + ['_key' => uniqid('field_', true)],
                    $definition->fields,
                );
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
            '_key' => uniqid('field_', true),
        ];
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    #[Computed]
    public function entryCount(): int
    {
        if (! $this->isEditing || ! $this->modelId) {
            return 0;
        }

        $collection = Collection::where('tenant_id', Auth::user()->selected_tenant_id)
            ->where('collection_key', mb_strtoupper(str_replace('-', '_', $this->modelId)))
            ->first();

        return $collection ? $collection->rows()->count() : 0;
    }

    public function store(): void
    {
        // Normalize filename: lowercase, replace underscores with hyphens
        $this->detailData['filename'] = mb_strtolower($this->detailData['filename']);
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

        // If there are renames and user hasn't confirmed yet, ask via modal
        if ($renames && ! $this->showRenameConfirmation) {
            $this->pendingRenames = $renames;
            $this->showRenameConfirmation = true;
            Noerd::modal('cms::collection-rename-confirmation', ['renames' => $renames]);

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
            fields: array_values(array_map(fn (array $field): array => Arr::except($field, ['_key']), $this->fields)),
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

        // A completed save resets the rename round trip and the rename baseline.
        $this->syncOriginalFieldNames();
        $this->pendingRenames = [];
        $this->showRenameConfirmation = false;

        $this->dispatch('listRefresh');
        $this->dispatch('refreshList-collection-definitions-list');
        $this->showSuccessIndicator = true;
    }

    #[On('collectionRenameConfirmed')]
    public function confirmRenameAndSave(): void
    {
        $this->renameFieldsInDatabase();
        $this->showRenameConfirmation = false;
        $this->syncOriginalFieldNames();
        $this->store();
    }

    #[On('collectionRenameSkipped')]
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
        $this->dispatch('refreshList-collection-definitions-list');
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

<x-noerd::page>
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
                        <tr wire:key="field-{{ $field['_key'] ?? $index }}" class="group hover:bg-brand-bg border border-black/10">
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
                                <x-noerd::button variant="icon"
                                                 size="sm"
                                                 icon="x-mark"
                                                 wire:click="removeField({{ $index }})"/>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <x-noerd::button variant="secondary"
                         icon="plus"
                         wire:click="addField"
                         class="mt-3">
            {{ __('Add Field') }}
        </x-noerd::button>
    </div>


    <x-slot:footer>
        <div class="flex items-center w-full gap-2">
            @if($isEditing)
                <div class="flex gap-2 mr-auto">
                    <x-noerd::button variant="secondary" wire:click="copy" wire:confirm="{{ __('Only the collection structure will be copied, not the entries. Continue?') }}">
                        {{ __('Copy') }}
                    </x-noerd::button>
                </div>
            @endif
            <x-noerd::delete-save-bar :showDelete="$isEditing" deleteMessage="{{ __('Warning: The collection and all associated entries (:count entries) will be permanently deleted. Continue?', ['count' => $this->entryCount]) }}" />
        </div>
    </x-slot:footer>
</x-noerd::page>
