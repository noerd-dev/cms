<?php

declare(strict_types=1);

namespace Noerd\Cms\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Noerd\Cms\Forms\FormMailer;
use Noerd\Cms\Models\FormRequest;
use Noerd\Support\TenantContext;
use Throwable;

/**
 * The confirmation of the visitor who sent a form (FormMailer::sendConfirmation()).
 * The notification of the form recipients is SendFormNotificationEmail.
 */
class SendFormConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        public FormRequest $formRequest,
    ) {}

    public function handle(FormMailer $mailer): void
    {
        // The submission knows its tenant: the job runs in it, wherever it was
        // dispatched from (public API, website form, queue worker).
        TenantContext::run((int) $this->formRequest->tenant_id, function () use ($mailer): void {
            $recipient = $mailer->sendConfirmation($this->formRequest);

            logger()->info($recipient === null ? 'No form confirmation to send' : 'Form confirmation email sent to the sender', [
                'form_request_id' => $this->formRequest->id,
                'form_type_id' => $this->formRequest->form_type_id,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        logger()->error('Form confirmation email failed permanently', [
            'form_request_id' => $this->formRequest->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
