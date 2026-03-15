<?php

use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Traits\NoerdDetail;
use Symfony\Component\Yaml\Yaml;

new class extends Component
{
    use NoerdDetail;

    #[Url(as: 'collectionDefinitionId', keep: false, except: '')]
    public $modelId = null;

    public array $collectionData = [
        'filename' => '',
        'title' => '',
        'titleList' => '',
        'buttonList' => '',
        'description' => '',
        'hasPage' => false,
    ];

    public array $fields = [];

    public bool $isEditing = false;

    public function mount(): void
    {
        $this->initDetail();
        $this->pageLayout = StaticConfigHelper::getComponentFields('collection-definition-detail');

        if ($this->modelId) {
            $this->isEditing = true;

            foreach ($this->pageLayout['fields'] as &$field) {
                if ($field['name'] === 'collectionData.filename') {
                    $field['readonly'] = true;
                    break;
                }
            }
            unset($field);
            $path = base_path('app-configs/cms/collections/' . $this->modelId . '.yml');

            if (file_exists($path)) {
                $content = Yaml::parseFile($path);
                $this->collectionData['filename'] = $this->modelId;
                $this->collectionData['title'] = $content['title'] ?? '';
                $this->collectionData['titleList'] = $content['titleList'] ?? '';
                $this->collectionData['buttonList'] = $content['buttonList'] ?? '';
                $this->collectionData['description'] = $content['description'] ?? '';
                $this->collectionData['hasPage'] = ! empty($content['hasPage']);

                $this->fields = [];
                foreach ($content['fields'] ?? [] as $field) {
                    $this->fields[] = [
                        'name' => preg_replace('/^(model\.|pageData\.)/', '', $field['name'] ?? ''),
                        'label' => $field['label'] ?? '',
                        'type' => $field['type'] ?? 'text',
                        'colspan' => $field['colspan'] ?? 6,
                    ];
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
        $this->collectionData['filename'] = mb_strtolower($this->collectionData['filename']);
        $this->collectionData['filename'] = preg_replace('/\.ya?ml$/i', '', $this->collectionData['filename']);
        $this->collectionData['filename'] = str_replace('_', '-', $this->collectionData['filename']);

        $rules = [
            'collectionData.filename' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/'],
            'collectionData.title' => ['required', 'string', 'max:255'],
            'collectionData.titleList' => ['required', 'string', 'max:255'],
        ];

        foreach ($this->fields as $index => $field) {
            $rules["fields.{$index}.name"] = ['required', 'string', 'max:255'];
            $rules["fields.{$index}.label"] = ['required', 'string', 'max:255'];
            $rules["fields.{$index}.type"] = ['required', 'string'];
        }

        $this->validate($rules);

        $filename = $this->collectionData['filename'];
        $path = base_path('app-configs/cms/collections/' . $filename . '.yml');

        // Prevent duplicate filenames when creating
        if (! $this->isEditing && file_exists($path)) {
            $this->addError('collectionData.filename', __('cms_file_already_exists'));

            return;
        }

        // Build YAML structure
        $key = mb_strtoupper(str_replace('-', '_', $filename));
        $yamlFields = [];
        foreach ($this->fields as $field) {
            $yamlFields[] = [
                'name' => 'pageData.' . $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'colspan' => (int) $field['colspan'],
            ];
        }

        $data = [
            'title' => $this->collectionData['title'],
            'titleList' => $this->collectionData['titleList'],
            'key' => $key,
            'buttonList' => $this->collectionData['buttonList'] ?: '',
            'description' => $this->collectionData['description'] ?: '',
            'hasPage' => (bool) $this->collectionData['hasPage'],
            'fields' => $yamlFields,
        ];

        $yamlContent = Yaml::dump($data, 4, 2);
        file_put_contents($path, $yamlContent);

        $this->isEditing = true;
        $this->modelId = $filename;

        $this->dispatch('listRefresh');
        $this->closeModalProcess('collection-definitions-list');
    }

    public function delete(): void
    {
        if (! $this->modelId) {
            return;
        }

        $path = base_path('app-configs/cms/collections/' . $this->modelId . '.yml');
        if (file_exists($path)) {
            unlink($path);
        }

        $this->closeModalProcess('collection-definitions-list');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('cms_label_collection_definition') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <div class="px-6 py-4">
        <h3 class="text-sm font-medium text-gray-700 mb-3">{{ __('cms_label_fields') }}</h3>

        @if(count($fields) === 0)
            <p class="text-sm text-gray-500 italic">{{ __('cms_label_no_fields') }}</p>
        @endif

        <div class="space-y-2">
            @foreach($fields as $index => $field)
                <div wire:key="field-{{ $index }}" class="flex items-center gap-2">
                    <div class="flex-1">
                        <input type="text" wire:model="fields.{{ $index }}.name"
                               placeholder="{{ __('cms_label_field_name') }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                        @error("fields.{$index}.name") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex-1">
                        <input type="text" wire:model="fields.{{ $index }}.label"
                               placeholder="{{ __('cms_label_field_label') }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                        @error("fields.{$index}.label") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="w-48">
                        <select wire:model="fields.{{ $index }}.type"
                                class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                            <option value="text">Text</option>
                            <option value="translatableText">Translatable Text</option>
                            <option value="translatableTextarea">Translatable Textarea</option>
                            <option value="translatableRichText">Translatable RichText</option>
                            <option value="image">Image</option>
                            <option value="email">E-Mail</option>
                            <option value="tel">Tel</option>
                            <option value="checkbox">Checkbox</option>
                        </select>
                    </div>
                    <div class="w-20">
                        <select wire:model="fields.{{ $index }}.colspan"
                                class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="6">6</option>
                            <option value="12">12</option>
                        </select>
                    </div>
                    <button type="button" wire:click="removeField({{ $index }})"
                            class="text-red-500 hover:text-red-700 p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>

        <button type="button" wire:click="addField"
                class="mt-3 inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            {{ __('cms_label_add_field') }}
        </button>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$isEditing" />
    </x-slot:footer>
</x-noerd::page>
