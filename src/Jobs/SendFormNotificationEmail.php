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
 * The notification of the form recipients about a submission
 * (FormMailer::sendNotification()) — it never goes to the visitor.
 */
class SendFormNotificationEmail implements ShouldQueue
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
        TenantContext::run((int) $this->formRequest->tenant_id, function () use ($mailer): void {
            $recipients = $mailer->sendNotification($this->formRequest);

            logger()->info($recipients === [] ? 'No form notification to send' : 'Form notification email sent to the recipients', [
                'form_request_id' => $this->formRequest->id,
                'form_type_id' => $this->formRequest->form_type_id,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        logger()->error('Form notification email failed permanently', [
            'form_request_id' => $this->formRequest->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
