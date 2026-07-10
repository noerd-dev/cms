<?php

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Noerd\Cms\Mail\FormConfirmation;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Communication\Services\Communicator;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    #[Url(as: 'formRequestId', keep: false, except: '')]
    public $modelId = null;

    public const DETAIL_CLASS = FormRequest::class;

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

    #[Computed]
    public function canResendNotification(): bool
    {
        if (! $this->detailData['form_type_id']) {
            return false;
        }

        $formType = FormType::find($this->detailData['form_type_id']);

        if (! $formType) {
            return false;
        }

        return $formType->send_email
            && ! empty($formType->notification_email)
            && ! empty($formType->email_subject)
            && ! empty($formType->email_body);
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
            $this->js("alert('" . __('Bitte warten Sie :seconds Sekunden, bevor Sie die Benachrichtigung erneut senden.', ['seconds' => $seconds]) . "')");

            return;
        }

        try {
            $formType = FormType::find($this->detailData['form_type_id']);

            $formRequest = FormRequest::find($this->modelId);

            if (! $formType || ! $formRequest) {
                $this->js("alert('" . __('Fehler: FormType oder FormRequest nicht gefunden.') . "')");

                return;
            }

            RateLimiter::hit($key, 30);

            $emailSubject = $formType->replacePlaceholders($formRequest, $formType->email_subject);
            $emailBody = $formType->replacePlaceholders($formRequest, $formType->email_body);

            app(Communicator::class)->send(
                mailable: new FormConfirmation(
                    $formRequest,
                    $emailSubject,
                    $emailBody,
                ),
                to: $formType->notification_email,
            );

            logger()->info('Notification email resent for form request', [
                'form_request_id' => $this->modelId,
                'form_type_id' => $formType->id,
                'notification_email' => $formType->notification_email,
                'resent_by' => auth()->id(),
            ]);

            $this->js("alert('" . __('Benachrichtigung wurde erneut an :email gesendet.', ['email' => $formType->notification_email]) . "')");
        } catch (\Exception $e) {
            logger()->error('Failed to resend notification email', [
                'form_request_id' => $this->modelId,
                'error' => $e->getMessage(),
            ]);

            $this->js("alert('" . __('Fehler beim Senden der Benachrichtigung.') . "')");
        }
    }

    public function delete(): void
    {
        $fr = FormRequest::find($this->modelId);
        if ($fr) {
            $fr->delete();
        }
        $this->closeModalProcess($this->getListComponent());
    }

    #[On('languageChanged')]
    public function languageChanged(): void
    {
        $this->dispatch('$refresh');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Form Request') }} #{{$detailData['id'] ?? ''}}</x-noerd::modal-title>
    </x-slot:header>

    <div class="p-4 mb-4 sm:p-8 relative overflow-hidden rounded-lg bg-gray-950/[2.5%] after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:inset-ring after:inset-ring-gray-950/5">
        <div class="text-sm text-gray-600 mb-4">
            <div><strong>{{ __('Created') }}:</strong>
                {{\Carbon\Carbon::parse($detailData['created_at'])->format('d.m.Y H:i')}}
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
                                    <span>{{ __('Benachrichtigung erneut senden') }}</span>
                                </template>
                                <template x-if="cooldown > 0">
                                    <span>{{ __('Benachrichtigung erneut senden') }} (<span x-text="cooldown"></span>s)</span>
                                </template>
                            </span>
                            <span wire:loading wire:target="resendNotificationEmail">
                                {{ __('Wird gesendet...') }}
                            </span>
                        </x-noerd::button>
                    </div>
                </div>
            @endif

            <x-noerd::delete-save-bar :showDelete="$modelId" :showSave="false" />
        </div>
    </x-slot:footer>
</x-noerd::page>
