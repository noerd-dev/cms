<?php

namespace Noerd\Cms\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Noerd\Cms\Mail\FormConfirmation;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\FormRequest;
use Noerd\Communication\Services\Communicator;
use Throwable;

class SendFormConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        public FormRequest $formRequest,
    ) {}

    public function handle(Communicator $communicator): void
    {
        // Load form type relationship
        $this->formRequest->loadMissing('formType');

        $formType = $this->formRequest->formType;

        // Check if email sending is enabled for this form type
        if (! $formType || ! $formType->send_email) {
            logger()->info('Email sending disabled for form type', [
                'form_request_id' => $this->formRequest->id,
                'form_type_id' => $formType?->id,
                'form_type_key' => $formType?->key,
            ]);

            return;
        }

        // Check if email subject and body are configured
        if (empty($formType->email_subject) || empty($formType->email_body)) {
            logger()->warning('Email subject or body not configured for form type', [
                'form_request_id' => $this->formRequest->id,
                'form_type_id' => $formType->id,
                'form_type_key' => $formType->key,
            ]);

            return;
        }

        // Replace placeholders in subject and body. The body is rendered as
        // HTML, so its substituted values must be escaped.
        $emailSubject = $formType->replacePlaceholders($this->formRequest, $formType->email_subject);
        $emailBody = $formType->replacePlaceholders($this->formRequest, $formType->email_body, escapeHtml: true);

        // Send email to customer (if email field exists in form data)
        if (isset($this->formRequest->data['email']) && filter_var($this->formRequest->data['email'], FILTER_VALIDATE_EMAIL)) {
            $customerEmail = $this->formRequest->data['email'];

            $communicator->send(
                mailable: new FormConfirmation(
                    $this->formRequest,
                    $emailSubject,
                    $emailBody,
                ),
                to: $customerEmail,
            );

            logger()->info('Form confirmation email sent to customer', [
                'form_request_id' => $this->formRequest->id,
                'form_type_id' => $formType->id,
            ]);
        }

        // Notify the recipients maintained in the CMS settings, falling back to
        // the form type's own notification address when none are configured.
        $notificationRecipients = CmsSetting::formRecipientsForTenant((int) $this->formRequest->tenant_id);

        if ($notificationRecipients === [] && $formType->notification_email) {
            $notificationRecipients = [$formType->notification_email];
        }

        if ($notificationRecipients !== []) {
            $communicator->send(
                mailable: new FormConfirmation(
                    $this->formRequest,
                    $emailSubject,
                    $emailBody,
                ),
                to: $notificationRecipients,
            );

            logger()->info('Form confirmation email sent to admin', [
                'form_request_id' => $this->formRequest->id,
                'form_type_id' => $formType->id,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        logger()->error('Form confirmation email failed permanently', [
            'form_request_id' => $this->formRequest->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
