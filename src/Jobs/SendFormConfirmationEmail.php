<?php

namespace Noerd\Cms\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Noerd\Cms\Mail\FormConfirmation;
use Noerd\Website\Models\FormRequest;

class SendFormConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FormRequest $formRequest,
    ) {}

    public function handle(): void
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

        // Replace placeholders in subject and body
        $emailSubject = $formType->replacePlaceholders($this->formRequest, $formType->email_subject);
        $emailBody = $formType->replacePlaceholders($this->formRequest, $formType->email_body);

        // Send email to customer (if email field exists in form data)
        if (isset($this->formRequest->data['email']) && filter_var($this->formRequest->data['email'], FILTER_VALIDATE_EMAIL)) {
            $customerEmail = $this->formRequest->data['email'];

            Mail::to($customerEmail)
                ->send(new FormConfirmation(
                    $this->formRequest,
                    $emailSubject,
                    $emailBody,
                ));

            logger()->info('Form confirmation email sent to customer', [
                'form_request_id' => $this->formRequest->id,
                'form_type_id' => $formType->id,
                'email' => $customerEmail,
            ]);
        }

        // Send second email to notification address if configured
        $notificationEmail = $formType->notification_email;
        if ($notificationEmail) {
            Mail::to($notificationEmail)
                ->send(new FormConfirmation(
                    $this->formRequest,
                    $emailSubject,
                    $emailBody,
                ));

            logger()->info('Form confirmation email sent to admin', [
                'form_request_id' => $this->formRequest->id,
                'form_type_id' => $formType->id,
                'notification_email' => $notificationEmail,
            ]);
        }
    }
}
