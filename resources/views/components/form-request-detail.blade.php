<?php

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Cms\Forms\FormMailer;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'formRequestId';

    public $detailModel = FormRequest::class;

    public function mount(): void
    {
        $this->initDetail();

        $formRequest = new FormRequest;
        if ($this->modelId) {
            $formRequest = FormRequest::find($this->modelId) ?? new FormRequest;
        }

        // Prepare view model
        $this->detailData = [
            'id' => $formRequest->id,
            'tenant_id' => $formRequest->tenant_id,
            'form_type_id' => $formRequest->form_type_id,
            'created_at' => $formRequest->created_at,
            'updated_at' => $formRequest->updated_at,
            'data' => is_string($formRequest->data) ? json_decode($formRequest->data, true) : ($formRequest->data ?? []),
        ];
    }

    /**
     * Resending writes on the request's behalf (a mail goes out, the log
     * records it): it needs the write permission on form requests, and the
     * form type is read from the stored request — never from the client state.
     */
    #[Computed]
    public function canResendNotification(): bool
    {
        if (! $this->canWriteObject()) {
            return false;
        }

        $formType = $this->storedFormType();

        if (! $formType) {
            return false;
        }

        // The notification goes to the form recipients only (FormMailer).
        return app(FormMailer::class)->canNotify($formType);
    }

    #[Computed]
    public function resendRateLimitKey(): string
    {
        return 'resend-notification:form-request:' . ($this->modelId ?? 'new') . ':' . auth()->id();
    }

    #[Computed]
    public function canSendNow(): bool
    {
        return $this->canResendNotification && ! RateLimiter::tooManyAttempts($this->resendRateLimitKey, 1);
    }

    #[Computed]
    public function resendCooldownSeconds(): int
    {
        if (! RateLimiter::tooManyAttempts($this->resendRateLimitKey, 1)) {
            return 0;
        }

        return RateLimiter::availableIn($this->resendRateLimitKey);
    }

    public function resendNotificationEmail(): void
    {
        if (! $this->canResendNotification) {
            return;
        }

        $key = $this->resendRateLimitKey;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            $this->dispatch('toast', [
                'title' => __('Please wait'),
                'description' => __('Please wait :seconds seconds before resending the notification.', ['seconds' => $seconds]),
            ]);

            return;
        }

        try {
            $formRequest = $this->modelId ? FormRequest::find($this->modelId) : null;
            $formType = $this->storedFormType();

            if (! $formType || ! $formRequest) {
                $this->dispatch('toast', [
                    'title' => __('Error'),
                    'description' => __('Form type or form request not found.'),
                ]);

                return;
            }

            RateLimiter::hit($key, 30);

            $recipients = app(FormMailer::class)->sendNotification($formRequest);

            logger()->info('Notification email resent for form request', [
                'form_request_id' => $this->modelId,
                'form_type_id' => $formType->id,
            ]);

            $this->dispatch('toast', [
                'title' => __('Notification sent'),
                'description' => __('Notification was resent to :email.', ['email' => implode(', ', $recipients)]),
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to resend notification email', [
                'form_request_id' => $this->modelId,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'title' => __('Error'),
                'description' => __('Failed to send the notification.'),
            ]);
        }
    }

    /**
     * The form type of the stored form request (tenant scoped).
     */
    private function storedFormType(): ?FormType
    {
        $formTypeId = $this->modelId ? FormRequest::query()->whereKey($this->modelId)->value('form_type_id') : null;

        return $formTypeId ? FormType::find($formTypeId) : null;
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Form Request') }} #{{$detailData['id'] ?? ''}}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="$modelId" :showBlock="false">
        <x-slot:tab1>
    <div class="p-4 mb-4 sm:p-8 relative overflow-hidden rounded-lg bg-gray-950/[2.5%] after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-gray-950/5">
        <div class="text-sm text-gray-600 mb-4">
            <div><strong>{{ __('Created') }}:</strong>
                {{ \Noerd\Helpers\FormatHelper::dateTime($detailData['created_at']) }}
            </div>
        </div>

        <div class="bg-white rounded border p-4">
            <div class="font-semibold mb-2">{{ __('Data') }}</div>
            @php($data = $detailData['data'] ?? [])
            @if(is_array($data))
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($data as $key => $value)
                        <div>
                            <dt class="text-gray-500 text-xs uppercase tracking-wide">{{$key}}</dt>
                            <dd class="text-sm mt-1">
                                @if(is_array($value))
                                    <pre class="bg-gray-50 p-2 rounded text-xs whitespace-pre-wrap">{{ json_encode($value, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                                @else
                                    {{$value}}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <pre class="bg-gray-50 p-4 rounded text-xs whitespace-pre-wrap">{{ json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>
    </div>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <div class="flex items-center w-full gap-2">
            @if($this->canResendNotification)
                <div class="flex gap-2 mr-auto">
                    <div x-data="{
                        cooldown: @js($this->canSendNow ? 0 : $this->resendCooldownSeconds),
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
                            wire:click="resendNotificationEmail"
                            wire:loading.attr="disabled"
                            wire:target="resendNotificationEmail"
                            x-bind:disabled="cooldown > 0"
                            :disabled="!$this->canSendNow"
                            x-on:click="if(cooldown <= 0) { cooldown = 30; startCountdown(); }">
                            <span wire:loading.remove wire:target="resendNotificationEmail">
                                <template x-if="cooldown <= 0">
                                    <span>{{ __('Resend notification') }}</span>
                                </template>
                                <template x-if="cooldown > 0">
                                    <span>{{ __('Resend notification') }} (<span x-text="cooldown"></span>s)</span>
                                </template>
                            </span>
                            <span wire:loading wire:target="resendNotificationEmail">
                                {{ __('Sending mail...') }}
                            </span>
                        </x-noerd::button>
                    </div>
                </div>
            @endif

            <x-noerd::delete-save-bar :showDelete="isset($modelId)" :showSave="false" />
        </div>
    </x-slot:footer>
</x-noerd::page>
