<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Noerd\Cms\Models\FormType;
use Noerd\Noerd\Traits\Noerd;

new class () extends Component {
    use Noerd;

    public const COMPONENT = 'form-type-detail';
    public const LIST_COMPONENT = 'form-types-list';
    public const ID = 'formTypeId';

    #[Url(keep: false, except: '')]
    public $formTypeId = null;

    public array $formType;
    public ?array $ymlConfig = null;
    public bool $showPreview = false;
    public bool $testEmailSending = false;

    #[Computed]
    public function canShowPreview(): bool
    {
        return ($this->formType['send_email'] ?? false)
            && ! empty($this->formType['email_body']);
    }

    #[Computed]
    public function testEmailRateLimitKey(): string
    {
        return 'test-email:form-type:' . auth()->id();
    }

    #[Computed]
    public function canSendTestEmail(): bool
    {
        return $this->canShowPreview && ! RateLimiter::tooManyAttempts($this->testEmailRateLimitKey, 1);
    }

    #[Computed]
    public function testEmailCooldownSeconds(): int
    {
        return RateLimiter::availableIn($this->testEmailRateLimitKey);
    }

    public function sendTestEmail(): void
    {
        if (! $this->canShowPreview) {
            return;
        }

        $key = $this->testEmailRateLimitKey;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            $this->js("alert('" . __('Bitte warten Sie :seconds Sekunden, bevor Sie eine weitere Test-E-Mail senden.', ['seconds' => $seconds]) . "')");

            return;
        }

        $this->testEmailSending = true;

        RateLimiter::hit($key, 30);

        $user = auth()->user();
        $sampleData = $this->getSampleEmailData();

        $subject = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $this->formType['email_subject'] ?? __('Test-E-Mail')
        );

        $subject = '[TEST] ' . $subject;

        $emailBody = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $this->formType['email_body'] ?? ''
        );

        $htmlContent = view('cms::emails.form-confirmation', [
            'emailBody' => $emailBody,
        ])->render();

        Mail::html($htmlContent, function ($message) use ($user, $subject) {
            $message->to($user->email)
                ->subject($subject);
        });

        $this->testEmailSending = false;

        $this->js("alert('" . __('Test-E-Mail wurde an :email gesendet.', ['email' => $user->email]) . "')");
    }

    public function getSampleEmailData(): array
    {
        $sampleData = [
            '{{form_title}}' => $this->formType['title'] ?? 'Formulartyp',
            '{{submission_date}}' => now()->format('d.m.Y H:i'),
        ];

        if ($this->formTypeId) {
            $model = FormType::find($this->formTypeId);
            if ($model) {
                $fieldPlaceholders = $model->getFieldPlaceholders();
                foreach ($fieldPlaceholders as $placeholder => $description) {
                    $sampleData[$placeholder] = 'Beispiel: ' . $description;
                }
            }
        }

        return $sampleData;
    }

    #[Computed]
    public function previewEmailHtml(): string
    {
        if (! $this->canShowPreview) {
            return '';
        }

        $emailBody = $this->formType['email_body'] ?? '';
        $sampleData = $this->getSampleEmailData();

        $processedBody = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $emailBody,
        );

        return view('cms::emails.form-confirmation', [
            'emailBody' => $processedBody,
        ])->render();
    }

    public function openPreview(): void
    {
        if ($this->canShowPreview) {
            $this->showPreview = true;
        }
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }

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

<div>
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
                    <x-noerd::input-label class="pb-2" value="{{ __('E-Mail-Inhalt (HTML)') }}"/>
                    <x-noerd::forms.quill
                        :field="'formType.email_body'"
                        :content="$formType['email_body'] ?? ''"/>
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
            <div class="flex items-center w-full gap-2">
                @if($this->canShowPreview)
                    <div class="flex gap-2 mr-auto">
                        <x-noerd::buttons.secondary wire:click="openPreview">
                            {{ __('E-Mail-Vorschau') }}
                        </x-noerd::buttons.secondary>

                        <x-noerd::buttons.secondary
                            wire:click="sendTestEmail"
                            wire:loading.attr="disabled"
                            wire:target="sendTestEmail"
                            :disabled="!$this->canSendTestEmail">
                            <span wire:loading.remove wire:target="sendTestEmail">
                                {{ __('Testemail senden') }}
                            </span>
                            <span wire:loading wire:target="sendTestEmail">
                                {{ __('Wird gesendet...') }}
                            </span>
                        </x-noerd::buttons.secondary>
                    </div>
                @endif

                <x-noerd::delete-save-bar :showDelete="false" class="ml-auto"/>
            </div>
        </x-slot:footer>
    </x-noerd::page>

    <!-- Email Preview Modal -->
    <div x-data="{ show: $wire.entangle('showPreview') }"
         x-show="show"
         x-effect="document.body.style.overflow = show ? 'hidden' : ''"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
         aria-modal="true"
         role="dialog"
         @keydown.escape.window="$wire.closePreview()"
         style="display: none;">

        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/60" @click="$wire.closePreview()"></div>

        <!-- Modal Content -->
        <div x-show="show"
             x-transition.scale.opacity
             class="relative w-full max-w-4xl max-h-[90vh] bg-white text-gray-800 border-[3px] border-gray-800 overflow-y-auto my-auto">

            <!-- Modal Header -->
            <div class="flex items-start justify-between p-4 sm:p-5 border-b border-gray-800/20">
                <div>
                    <h3 class="text-2xl font-semibold">{{ __('E-Mail-Vorschau') }}</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('So wird die E-Mail mit Beispieldaten angezeigt') }}
                    </p>
                </div>
                <button
                    class="ml-4 inline-flex items-center justify-center border-[3px] border-gray-800 p-2 text-sm bg-white hover:bg-gray-800 hover:text-white transition-colors"
                    @click="$wire.closePreview()"
                    aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <!-- Email Subject Preview -->
            @if(!empty($formType['email_subject']))
                <div class="px-4 sm:px-6 py-3 bg-gray-50 border-b border-gray-800/20">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        {{ __('Betreff') }}
                    </div>
                    <div class="text-base font-medium text-gray-900">
                        {{ str_replace(
                            array_keys($this->getSampleEmailData()),
                            array_values($this->getSampleEmailData()),
                            $formType['email_subject']
                        ) }}
                    </div>
                </div>
            @endif

            <!-- Email Body Preview -->
            <div class="p-4 sm:p-6">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    {{ __('Inhalt') }}
                </div>
                <div class="border border-gray-300 rounded-lg overflow-hidden">
                    <!-- Render the actual email HTML -->
                    <iframe
                        srcdoc="{!! str_replace('"', '&quot;', $this->previewEmailHtml) !!}"
                        class="w-full h-[500px] bg-white"
                        sandbox="allow-same-origin"
                        title="Email Preview">
                    </iframe>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end gap-3 p-4 sm:p-5 border-t border-gray-800/20">
                <x-noerd::buttons.secondary @click="$wire.closePreview()">
                    {{ __('Schließen') }}
                </x-noerd::buttons.secondary>
            </div>
        </div>
    </div>
</div>
