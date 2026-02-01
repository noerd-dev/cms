<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;
use Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const DETAIL_COMPONENT = 'collection-detail';
    public const LIST_COMPONENT = 'collections-list';

    public ?string $fileName = null;
    public string $yamlContent = '';
    public string $originalFileName = '';
    public bool $isNewFile = false;

    public function mount(): void
    {
        $this->fileName = $this->fileName ?? request()->get('fileName');

        if ($this->fileName) {
            $this->originalFileName = $this->fileName;
            $this->loadFile();
        } else {
            $this->isNewFile = true;
            $this->fileName = '';
            $this->yamlContent = $this->getExampleYaml();
        }
    }

    private function loadFile(): void
    {
        $filePath = base_path('app-configs/cms/collections/' . $this->fileName);

        if (File::exists($filePath)) {
            $this->yamlContent = File::get($filePath);
        } else {
            $this->dispatch('noerd-notification', [
                'type' => 'error',
                'message' => __('cms_file_not_found')
            ]);
            $this->closeModalProcess(self::LIST_COMPONENT);
        }
    }

    private function getExampleYaml(): string
    {
        return "title: 'Neue Collection'
titleList: 'Neue Collection Liste'
key: 'NEW_COLLECTION'
buttonList: 'Neuer Eintrag'
description: 'Beschreibung der Collection'
hasPage: true
fields:
  - { name: model.name, label: Name, type: translatableText, colspan: 6 }
  - { name: model.description, label: Beschreibung, type: translatableTextarea, colspan: 12 }
  - { name: image, label: Bild, type: image, colspan: 6 }
";
    }

    public function store(): void
    {
        $this->validate([
            'fileName' => ['required', 'string'],
            'yamlContent' => ['required', 'string'],
        ], [
            'fileName.required' => __('Filename is required.'),
            'fileName.regex' => __('Filename may only contain letters, numbers, hyphens and underscores.'),
            'yamlContent.required' => __('YAML content is required.'),
        ]);

        // Ensure filename has .yml extension
        $fileName = $this->fileName;
        if (!Str::endsWith($fileName, '.yml')) {
            $fileName .= '.yml';
        }

        // Check if filename changed and new file already exists
        if ($this->isNewFile || $fileName !== $this->originalFileName) {
            $filePath = base_path('app-configs/cms/collections/' . $fileName);
            if (File::exists($filePath)) {
                $this->addError('fileName', __('cms_file_already_exists'));
                return;
            }
        }

        // Basic YAML syntax validation
        if (!$this->validateYamlSyntax($this->yamlContent)) {
            $this->addError('yamlContent', __('cms_invalid_yaml_syntax'));
            return;
        }

        // Save file
        $filePath = base_path('app-configs/cms/collections/' . $fileName);
        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $this->yamlContent);

        // If filename changed, delete old file
        if (!$this->isNewFile && $fileName !== $this->originalFileName) {
            $oldFilePath = base_path('app-configs/cms/collections/' . $this->originalFileName);
            if (File::exists($oldFilePath)) {
                File::delete($oldFilePath);
            }
        }

        $this->dispatch('noerd-notification', [
            'type' => 'success',
            'message' => $this->isNewFile ? __('Collection file was successfully created.') : __('Collection file was successfully saved.')
        ]);

        $this->showSuccessIndicator = true;
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    public function delete(): void
    {
        if (!$this->isNewFile && $this->originalFileName) {
            $filePath = base_path('app-configs/cms/collections/' . $this->originalFileName);

            if (File::exists($filePath)) {
                File::delete($filePath);
                $this->dispatch('noerd-notification', [
                    'type' => 'success',
                    'message' => __('Collection file was successfully deleted.')
                ]);
            }
        }

        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    private function validateYamlSyntax(string $yamlContent): bool
    {
        // Basic YAML validation without yaml_parse extension
        $lines = explode("\n", $yamlContent);
        $indentStack = [0];

        foreach ($lines as $lineNumber => $line) {
            $trimmed = trim($line);

            // Skip empty lines and comments
            if (empty($trimmed) || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Calculate indentation
            $indent = strlen($line) - strlen(ltrim($line));

            // Check for valid indentation (must be multiple of 2)
            if ($indent % 2 !== 0) {
                return false;
            }

            // Basic structure checks
            if (str_contains($line, ':')) {
                // Key-value pair
                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    return false;
                }
            }

            // Check for tabs (YAML doesn't allow tabs for indentation)
            if (str_contains($line, "\t")) {
                return false;
            }
        }

        return true;
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>
            {{ $isNewFile ? __('cms_create_new_collection') : __('cms_edit_collection') . ': ' . $originalFileName }}
        </x-noerd::modal-title>
    </x-slot:header>

    <div class="space-y-6">
        <!-- Filename Input -->
        <div class="grid gap-2">
            <label class="text-sm font-medium text-zinc-700">{{ __('cms_filename') }}</label>
            <input
                wire:model="fileName"
                type="text"
                placeholder="collection-name"
                @if(!$isNewFile) readonly @endif
                class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent {{ !$isNewFile ? 'bg-zinc-100 cursor-not-allowed' : '' }}"
            />
            @error('fileName')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            @if(!$isNewFile)
                <p class="text-sm text-zinc-500">{{ __('cms_filename_cannot_be_changed') }}</p>
            @endif
        </div>

        <!-- YAML Editor -->
        <div class="grid gap-2">
            <label class="text-sm font-medium text-zinc-700">{{ __('cms_yaml_content') }}</label>
            <textarea
                wire:model="yamlContent"
                rows="20"
                class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                style="white-space: pre; font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;"
            ></textarea>
            @error('yamlContent')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="text-sm text-zinc-500">
                {{ __('cms_edit_yaml_content') }}
                {{ __('cms_ensure_correct_yaml_syntax') }}
            </p>
        </div>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="!$isNewFile" />
    </x-slot:footer>
</x-noerd::page>
