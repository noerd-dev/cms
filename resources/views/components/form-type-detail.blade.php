<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Models\FormType;
use Noerd\Traits\HasEmailPreview;
use Noerd\Traits\NoerdDetail;

new class () extends Component {
    use HasEmailPreview;
    use NoerdDetail;

    public ?string $detailPrimary = 'formTypeId';

    public $detailModel = FormType::class;

    public ?array $ymlConfig = null;

    protected function getEmailData(): array
    {
        return $this->detailData;
    }

    protected function getEmailRateLimitPrefix(): string
    {
        return 'form-type:' . ($this->modelId ?? 'new');
    }

    protected function getEmailViewName(): string
    {
        return 'cms::emails.form-confirmation';
    }

    public function getSampleEmailData(): array
    {
        $sampleData = [
            '{{form_title}}' => $this->detailData['title'] ?? __('Form Type'),
            '{{submission_date}}' => \Noerd\Helpers\FormatHelper::documentDateTime(now()),
        ];

        if ($this->modelId) {
            $formType = FormType::find($this->modelId);
            if ($formType) {
                $fieldPlaceholders = $formType->getFieldPlaceholders();
                foreach ($fieldPlaceholders as $placeholder => $description) {
                    $sampleData[$placeholder] = __('Example') . ': ' . __($description);
                }
            }
        }

        return $sampleData;
    }

    #[Computed]
    public function emailPlaceholders(): array
    {
        $placeholders = FormType::getEmailPlaceholders();

        if ($this->modelId) {
            $formType = FormType::find($this->modelId);
            if ($formType) {
                $fieldPlaceholders = $formType->getFieldPlaceholders();
                $placeholders = array_merge($placeholders, $fieldPlaceholders);
            }
        }

        return $placeholders;
    }

    public function mount(): void
    {
        $this->initDetail();

        $formType = new FormType;
        if ($this->modelId) {
            $formType = FormType::find($this->modelId) ?? new FormType;
        }

        $this->detailData = $formType->toArray();
        $this->ymlConfig = $formType->loadYmlConfig();
    }

    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        $this->validate([
            'detailData.send_email' => ['boolean'],
            'detailData.email_subject' => ['nullable', 'string', 'max:255'],
            'detailData.email_body' => ['nullable', 'string'],
            'detailData.notification_email' => ['nullable', 'email', 'max:255'],
        ]);

        $formType = FormType::find($this->modelId);

        if (! $formType) {
            return;
        }

        $formType->update([
            'send_email' => $this->detailData['send_email'] ?? false,
            'email_subject' => $this->detailData['email_subject'] ?? null,
            'email_body' => $this->detailData['email_body'] ?? null,
            'notification_email' => $this->detailData['notification_email'] ?? null,
        ]);

        $this->storeProcess($formType);
    }
} ?>

<x-noerd::page>
        <x-slot:header>
            <x-noerd::modal-title>{{ __('Form Type') }}</x-noerd::modal-title>
        </x-slot:header>

        <div>
            <x-noerd::tab-content :layout="$pageLayout">
                <x-slot:prependTab1>
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
                                    {{ __('Hybrid configuration') }}
                                </p>
                                <p class="text-sm text-blue-700 mt-1">
                                    {{ __('Form fields are managed via YML files. Email texts can be edited directly here.') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Basic Information (Read-Only) --}}
                    <div
                        class="space-y-4 mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <h3 class="text-base font-semibold text-gray-900">{{ __('Basic information (from YML)') }}</h3>

                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <label class="font-medium text-gray-600">{{ __('Key') }}</label>
                                <p class="mt-1 text-gray-900">{{ $detailData['key'] ?? '-' }}</p>
                            </div>
                            <div>
                                <label class="font-medium text-gray-600">{{ __('Title') }}</label>
                                <p class="mt-1 text-gray-900">{{ $detailData['title'] ?? '-' }}</p>
                            </div>
                            <div>
                                <label class="font-medium text-gray-600">{{ __('Description') }}</label>
                                <p class="mt-1 text-gray-900">{{ $detailData['description'] ?? '-' }}</p>
                            </div>
                        </div>

                    </div>
                </x-slot:prependTab1>

                <x-slot:tab1>
                    <div class="mt-4">
                        <x-noerd::input-label class="pb-2" value="{{ __('Email body') }}"/>
                        <x-noerd::forms.tiptap
                            :field="'detailData.email_body'"
                            :content="$detailData['email_body'] ?? ''"/>
                    </div>

                    {{-- Email Placeholders --}}
                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h4 class="text-sm font-semibold text-blue-900 mb-2">
                            {{ __('Available placeholders for email content') }}
                        </h4>
                        <div class="text-sm text-blue-800 space-y-1">
                            @foreach($this->emailPlaceholders as $placeholder => $description)
                                <div class="flex gap-2">
                                    <code class="bg-blue-100 px-2 py-1 rounded">{{ $placeholder }}</code>
                                    <span>{{ __($description) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-slot:tab1>
            </x-noerd::tab-content>
        </div>

        <x-slot:footer>
            <div class="flex items-center w-full gap-2">
                @if($this->canShowPreview)
                    <div class="flex gap-2 mr-auto">
                        <x-noerd::button variant="secondary" wire:click="openPreview">
                            {{ __('Email preview') }}
                        </x-noerd::button>

                        <div x-data="{
                            cooldown: @js($this->canSendTestEmail ? 0 : $this->testEmailCooldownSeconds),
                            interval: null,
                            init() {
                                if (this.cooldown > 0) {
                                    this.startCountdown();
                                }
                            },
                            startCountdown() {
                                this.interval = setInterval(() => {
                                    this.cooldown--;
                                    if (this.cooldown <= 0) {
                                        clearInterval(this.interval);
                                        $wire.$refresh();
                                    }
                                }, 1000);
                            }
                        }">
                            <x-noerd::button variant="secondary"
                                wire:click="sendTestEmail"
                                wire:loading.attr="disabled"
                                wire:target="sendTestEmail"
                                x-bind:disabled="cooldown > 0"
                                :disabled="!$this->canSendTestEmail">
                                <span wire:loading.remove wire:target="sendTestEmail">
                                    <template x-if="cooldown <= 0">
                                        <span>{{ __('Send test email') }}</span>
                                    </template>
                                    <template x-if="cooldown > 0">
                                        <span>{{ __('Send test email') }} (<span x-text="cooldown"></span>s)</span>
                                    </template>
                                </span>
                                <span wire:loading wire:target="sendTestEmail">
                                    {{ __('Sending...') }}
                                </span>
                            </x-noerd::button>
                        </div>
                    </div>
                @endif

                <x-noerd::delete-save-bar :showDelete="false" class="ml-auto"/>
            </div>
    </x-slot:footer>
</x-noerd::page>
