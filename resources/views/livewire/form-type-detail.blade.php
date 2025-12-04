<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Models\FormType;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {
    use Noerd;

    public const COMPONENT = 'form-type-detail';
    public const LIST_COMPONENT = 'form-types-list';
    public const ID = 'formTypeId';

    #[Url(keep: false, except: '')]
    public $formTypeId = null;

    public array $formType;
    public ?array $ymlConfig = null;

    #[Computed]
    public function emailPlaceholders(): array
    {
        $placeholders = FormType::getEmailPlaceholders();

        if ($this->formTypeId) {
            $model = FormType::find($this->formTypeId);
            if ($model) {
                $fieldPlaceholders = $model->getFieldPlaceholders();
                $placeholders = array_merge($placeholders, $fieldPlaceholders);
            }
        }

        return $placeholders;
    }

    public function mount(FormType $model): void
    {
        if ($this->modelId) {
            $model = FormType::find($this->modelId);
        }

        $this->mountModalProcess(self::COMPONENT, $model);
        $this->formType = $model->toArray();
        $this->ymlConfig = $model->loadYmlConfig();
        $this->modalTitle = __('Formulartyp') . ' ' . ($this->formType['title'] ?? '');
    }

    public function store(): void
    {
        $this->validate([
            'formType.send_email' => ['boolean'],
            'formType.email_subject' => ['nullable', 'string', 'max:255'],
            'formType.email_body' => ['nullable', 'string'],
            'formType.notification_email' => ['nullable', 'email', 'max:255'],
        ]);

        $formType = FormType::find($this->formTypeId);

        if ($formType) {
            $formType->update([
                'send_email' => $this->formType['send_email'] ?? false,
                'email_subject' => $this->formType['email_subject'] ?? null,
                'email_body' => $this->formType['email_body'] ?? null,
                'notification_email' => $this->formType['notification_email'] ?? null,
            ]);

            $this->showSuccessIndicator = true;

            logger()->info('FormType email configuration updated', [
                'form_type_id' => $formType->id,
                'key' => $formType->key,
            ]);
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Formulartyp') }}</x-noerd::modal-title>
    </x-slot:header>

    <div>
        <div x-show="currentTab === 1">
            {{-- Warning Banner --}}
            <div class="mb-6 mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                              clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-blue-900">
                            {{ __('Hybrid-Konfiguration') }}
                        </p>
                        <p class="text-sm text-blue-700 mt-1">
                            {{ __('Formularfelder werden über YML-Dateien verwaltet. E-Mail-Texte können hier direkt bearbeitet werden.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Basic Information (Read-Only) --}}
            <div
                class="space-y-4 mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Grundinformationen (aus YML)') }}</h3>

                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <label class="font-medium text-gray-600 dark:text-gray-400">{{ __('Key') }}</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $formType['key'] ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="font-medium text-gray-600 dark:text-gray-400">{{ __('Titel') }}</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $formType['title'] ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="font-medium text-gray-600 dark:text-gray-400">{{ __('Beschreibung') }}</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $formType['description'] ?? '-' }}</p>
                    </div>
                </div>

            </div>

            {{-- Email Configuration (Editable) --}}
            @include('noerd::components.detail.block', $pageLayout)

            <div class="mt-4">
                <x-noerd::input-label class="pb-2" value="{{ __('E-Mail-Inhalt') }}"/>
                <textarea
                    wire:model="formType.email_body"
                    rows="10"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-800 dark:text-gray-100"
                    placeholder="{{ __('HTML-Inhalt der E-Mail...') }}"
                ></textarea>
            </div>

            {{-- Email Placeholders --}}
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
                    {{ __('Verfügbare Platzhalter für E-Mail-Inhalt') }}
                </h4>
                <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                    @foreach($this->emailPlaceholders as $placeholder => $description)
                        <div class="flex gap-2">
                            <code class="bg-blue-100 dark:bg-blue-800 px-2 py-1 rounded">{{ $placeholder }}</code>
                            <span>{{ $description }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false"/>
    </x-slot:footer>
</x-noerd::page>
