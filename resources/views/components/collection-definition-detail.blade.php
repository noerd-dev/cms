<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\CollectionDefinitionData;
use Noerd\Facades\Noerd;
use Noerd\Helpers\AccessHelper;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    /**
     * A field name: an identifier (a hyphen is allowed — older definitions use
     * it). Never a dotted path: a field name becomes a key of the entry form.
     */
    private const FIELD_NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]*$/';

    /**
     * Keys of the page record the entry form holds itself — no field may
     * claim one of them.
     */
    private const RESERVED_FIELD_NAMES = ['id', 'tenant_id', 'content_id', 'collection_id', 'data', 'sort', 'custom_attributes', 'created_at', 'updated_at'];

    public ?string $detailPrimary = 'collectionDefinitionId';

    public array $fields = [];

    /**
     * Server state: whether an existing definition is open. Locked — a client
     * flipping it would turn an edit into a create.
     */
    #[Locked]
    public bool $isEditing = false;

    /**
     * The stored name of every field row, index-aligned with $fields. Locked:
     * the renames are derived from it.
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $originalFieldNames = [];

    /** True while the rename question is open — only then does an answer proceed. */
    #[Locked]
    public bool $showRenameConfirmation = false;

    /**
     * The renames shown in the question (old name => new name). Display only:
     * the answer derives the renames again from the stored definition.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $pendingRenames = [];

    /**
     * The record id is the definition FILENAME, not a model key, so the
     * definition cannot be declared as $detailModel — the object permission is
     * resolved against the CollectionDefinition model explicitly.
     */
    public function canSaveObject(): bool
    {
        return $this->modelId
            ? AccessHelper::canWriteObject(CollectionDefinition::class)
            : AccessHelper::canCreateObject(CollectionDefinition::class);
    }

    public function canDeleteObject(): bool
    {
        return AccessHelper::canDeleteObject(CollectionDefinition::class);
    }

    public function mount(): void
    {
        $this->initDetail();

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
                    fn(array $field): array => $field + ['_key' => uniqid('field_', true)],
                    $definition->fields,
                );
                $this->syncOriginalFieldNames();
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
        unset($this->fields[$index], $this->originalFieldNames[$index]);
        $this->fields = array_values($this->fields);
        // Keep the stored names aligned with the rows, or the row that moved
        // up would count as renamed.
        $this->originalFieldNames = array_values($this->originalFieldNames);
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
        $this->save(applyRenames: null);
    }

    #[On('collectionRenameConfirmed')]
    public function confirmRenameAndSave(): void
    {
        if (! $this->showRenameConfirmation) {
            return;
        }

        $this->save(applyRenames: true);
    }

    #[On('collectionRenameSkipped')]
    public function skipRenameAndSave(): void
    {
        if (! $this->showRenameConfirmation) {
            return;
        }

        $this->save(applyRenames: false);
    }

    public function copy(): void
    {
        // copy() is not covered by the generic store/delete guard.
        if (! $this->modelId || ! AccessHelper::canCreateObject(CollectionDefinition::class)) {
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
        $this->dispatch('refreshList-cms::collection-definitions-list');
        $this->closeModalProcess('cms::collection-definitions-list');
    }

    public function delete(): void
    {
        if (! $this->modelId || ! $this->canDeleteObject()) {
            return;
        }

        $repository = app(CollectionDefinitionRepositoryContract::class);

        $collectionKey = mb_strtoupper(str_replace('-', '_', $this->modelId));
        // Through the models: the entries take their contents, elements and
        // element collections with them (Collection::deleting).
        Collection::where('tenant_id', Auth::user()->selected_tenant_id)
            ->where('collection_key', $collectionKey)
            ->get()
            ->each
            ->delete();

        $repository->delete($this->modelId);

        $this->closeModalProcess('cms::collection-definitions-list');
    }

    /**
     * Checks the permission, validates, asks about renamed fields and writes
     * the definition. Nothing is touched before the permission and the
     * validation passed; the renames are derived on the server (stored
     * definition vs. the submitted rows) and the entries' data keys are
     * renamed in the same transaction that saves the definition.
     *
     * @param  bool|null  $applyRenames  null = not asked yet, true/false = the answer
     */
    private function save(?bool $applyRenames): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        // Normalize filename: lowercase, replace underscores with hyphens
        $this->detailData['filename'] = str_replace('_', '-', mb_strtolower((string) ($this->detailData['filename'] ?? '')));

        $repository = app(CollectionDefinitionRepositoryContract::class);
        $storedFields = $this->storedFields($repository);

        $this->validate($this->rules($storedFields));

        $filename = $this->detailData['filename'];

        // Prevent duplicate filenames (when creating or renaming)
        $isRenaming = $this->isEditing && $filename !== $this->modelId;
        if ((! $this->isEditing || $isRenaming) && $repository->exists($filename)) {
            $this->addError('detailData.filename', __('A file with this name already exists.'));

            return;
        }

        $renames = $this->derivedRenames($storedFields);

        // Ask first — and again when the renames changed since the question.
        if ($renames !== [] && ($applyRenames === null || $renames !== $this->pendingRenames)) {
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
            fields: $this->sanitizedFields($storedFields),
        );

        $originalFilename = $this->isEditing ? (string) $this->modelId : null;
        $tenantId = Auth::user()->selected_tenant_id;

        DB::transaction(function () use ($repository, $data, $originalFilename, $isRenaming, $key, $renames, $applyRenames, $tenantId): void {
            if ($renames !== [] && $applyRenames === true && $originalFilename !== null) {
                $this->renameFieldsInDatabase(mb_strtoupper(str_replace('-', '_', $originalFilename)), $renames);
            }

            $repository->save($data, originalFilename: $originalFilename);

            // Update cms_collections table collection_key on rename (per-tenant scope)
            if ($isRenaming) {
                Collection::where('tenant_id', $tenantId)
                    ->where('collection_key', mb_strtoupper(str_replace('-', '_', (string) $originalFilename)))
                    ->update(['collection_key' => $key]);
            }

            // Ensure collection DB record exists with created_by
            if ($originalFilename === null) {
                Collection::firstOrCreate([
                    'tenant_id' => $tenantId,
                    'collection_key' => $key,
                ], [
                    'name' => $this->detailData['titleList'],
                    'created_by' => Auth::id(),
                ]);
            }
        });

        $this->isEditing = true;
        $this->modelId = $filename;

        // A completed save resets the rename round trip and the rename baseline.
        $this->syncOriginalFieldNames();
        $this->pendingRenames = [];
        $this->showRenameConfirmation = false;

        $this->dispatch('listRefresh');
        $this->dispatch('refreshList-cms::collection-definitions-list');
        $this->showSuccessIndicator = true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $storedFields
     * @return array<string, array<int, mixed>>
     */
    private function rules(array $storedFields): array
    {
        $rules = [
            'detailData.filename' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/'],
            'detailData.title' => ['required', 'string', 'max:255'],
            'detailData.titleList' => ['required', 'string', 'max:255'],
            'detailData.description' => ['nullable', 'string', 'max:2000'],
            'detailData.hasPage' => ['boolean'],
        ];

        foreach ($this->fields as $index => $field) {
            // A type outside the offered list is only kept for a field that
            // already carries it — looked up by the STORED name.
            $allowedTypes = array_keys((array) config('cms.collection_field_types', []));
            $storedType = $storedFields[$this->originalFieldNames[$index] ?? '']['type'] ?? null;
            if (is_string($storedType) && $storedType !== '') {
                $allowedTypes[] = $storedType;
            }

            $rules["fields.{$index}.name"] = ['required', 'string', 'max:64', 'regex:' . self::FIELD_NAME_PATTERN, Rule::notIn(self::RESERVED_FIELD_NAMES)];
            $rules["fields.{$index}.label"] = ['required', 'string', 'max:255'];
            $rules["fields.{$index}.type"] = ['required', 'string', Rule::in(array_values(array_unique($allowedTypes)))];
            $rules["fields.{$index}.colspan"] = ['nullable', 'integer', 'between:1,12'];
        }

        // Two fields with one name would share one value of every entry.
        $rules['fields.*.name'] = ['distinct'];

        return $rules;
    }

    /**
     * The renamed fields (stored name => new name), derived from the locked
     * row-to-stored-name map against the stored definition.
     *
     * @param  array<string, array<string, mixed>>  $storedFields
     * @return array<string, string>
     */
    private function derivedRenames(array $storedFields): array
    {
        if (! $this->isEditing) {
            return [];
        }

        $renames = [];

        foreach ($this->originalFieldNames as $index => $oldName) {
            if ($oldName === '' || ! array_key_exists($oldName, $storedFields) || ! isset($this->fields[$index])) {
                continue;
            }

            $newName = (string) ($this->fields[$index]['name'] ?? '');

            if ($newName !== $oldName && preg_match(self::FIELD_NAME_PATTERN, $newName) === 1) {
                $renames[$oldName] = $newName;
            }
        }

        return $renames;
    }

    /**
     * The fields of the stored definition, keyed by name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function storedFields(CollectionDefinitionRepositoryContract $repository): array
    {
        if (! $this->isEditing || ! $this->modelId) {
            return [];
        }

        return collect($repository->find((string) $this->modelId)?->fields ?? [])->keyBy('name')->all();
    }

    /**
     * What is written: name, label, type and colspan come from the form, every
     * other key of a field (the rows of an element collection, options, …)
     * only from the stored definition — resolved by the name the field had
     * BEFORE a rename — because the form is client state.
     *
     * @param  array<string, array<string, mixed>>  $storedFields
     * @return array<int, array<string, mixed>>
     */
    private function sanitizedFields(array $storedFields): array
    {
        $fields = [];

        foreach (array_values($this->fields) as $index => $field) {
            $stored = $storedFields[$this->originalFieldNames[$index] ?? ''] ?? [];

            $fields[] = [
                ...Arr::except($stored, ['name', 'label', 'type', 'colspan', '_key']),
                'name' => (string) $field['name'],
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'colspan' => (int) ($field['colspan'] ?? 12) ?: 12,
            ];
        }

        return $fields;
    }

    private function syncOriginalFieldNames(): void
    {
        $this->fields = array_values($this->fields);
        $this->originalFieldNames = [];
        foreach ($this->fields as $index => $field) {
            $this->originalFieldNames[$index] = (string) ($field['name'] ?? '');
        }
    }

    /**
     * Renames the data keys of the collection's entries. All renames apply at
     * once, so swapping two names works; a target key an entry already holds
     * (data of a removed field) is never overwritten.
     *
     * @param  array<string, string>  $renames
     */
    private function renameFieldsInDatabase(string $collectionKey, array $renames): void
    {
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
            $data = is_array($page->data) ? $page->data : [];
            $moved = [];

            foreach ($renames as $oldKey => $newKey) {
                if (array_key_exists($oldKey, $data)) {
                    $moved[$newKey] = $data[$oldKey];
                    unset($data[$oldKey]);
                }
            }

            if ($moved === []) {
                continue;
            }

            foreach ($moved as $newKey => $value) {
                if (! array_key_exists($newKey, $data)) {
                    $data[$newKey] = $value;
                }
            }

            $page->data = $data;
            $page->saveQuietly();
        }
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
                            {{ __('Field type') }}
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
                                    @foreach (config('cms.collection_field_types', []) as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}">{{ __($typeLabel) }}</option>
                                    @endforeach
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
            <x-noerd::delete-save-bar :showDelete="$isEditing" deleteMessage="{{ __('Warning: The collection and all associated entries (:count entries) will be permanently deleted. Continue?', ['count' => $this->entryCount]) }}" />
        </div>
    </x-slot:footer>
</x-noerd::page>
