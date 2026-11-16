<?php

declare(strict_types=1);

namespace Noerd\Cms\Forms;

use Noerd\Cms\Mail\FormConfirmation;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Communication\Services\Communicator;

/**
 * The two mails of a form submission — and the ONE home of who gets which:
 *
 * - the NOTIFICATION (`send_email`, `email_subject`, `email_body`) goes to the
 *   form recipients of the CMS settings, else to the form type's
 *   `notification_email` — never to an address the visitor typed
 * - the CONFIRMATION (`send_confirmation`, `confirmation_subject`,
 *   `confirmation_body`) goes to the visitor: the valid address in the field
 *   `confirmation_field` (default `email`) of the submission
 *
 * Both run in the submission's tenant (the jobs bring the context). The
 * subject keeps the values as they are, the body is HTML and escapes them.
 */
final class FormMailer
{
    public function __construct(private readonly Communicator $communicator) {}

    /**
     * The recipients of a form type's notification.
     *
     * @return list<string>
     */
    public function notificationRecipients(FormType $formType): array
    {
        $recipients = CmsSetting::formRecipientsForTenant((int) $formType->tenant_id);

        if ($recipients === [] && is_string($formType->notification_email) && filter_var($formType->notification_email, FILTER_VALIDATE_EMAIL)) {
            $recipients = [$formType->notification_email];
        }

        return $recipients;
    }

    public function canNotify(FormType $formType): bool
    {
        return $formType->send_email
            && ! empty($formType->email_subject)
            && ! empty($formType->email_body)
            && $this->notificationRecipients($formType) !== [];
    }

    /**
     * The visitor's address of a submission, or null when the confirmation is
     * switched off, has no text or the submission carries no valid address.
     */
    public function confirmationRecipient(FormRequest $formRequest, FormType $formType): ?string
    {
        if (! $formType->send_confirmation || empty($formType->confirmation_subject) || empty($formType->confirmation_body)) {
            return null;
        }

        $address = is_array($formRequest->data) ? ($formRequest->data[$formType->confirmationField()] ?? null) : null;

        return is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : null;
    }

    /**
     * @return list<string> the recipients the notification went to (empty: none sent)
     */
    public function sendNotification(FormRequest $formRequest): array
    {
        $formType = $this->formTypeOf($formRequest);

        if ($formType === null || ! $this->canNotify($formType)) {
            return [];
        }

        $recipients = $this->notificationRecipients($formType);

        $this->communicator->send(
            mailable: new FormConfirmation(
                $formRequest,
                $formType->replacePlaceholders($formRequest, (string) $formType->email_subject),
                $formType->replacePlaceholders($formRequest, (string) $formType->email_body, escapeHtml: true),
            ),
            to: $recipients,
            model: $formRequest,
        );

        return $recipients;
    }

    /**
     * @return string|null the visitor's address the confirmation went to
     */
    public function sendConfirmation(FormRequest $formRequest): ?string
    {
        $formType = $this->formTypeOf($formRequest);
        $recipient = $formType === null ? null : $this->confirmationRecipient($formRequest, $formType);

        if ($recipient === null) {
            return null;
        }

        $this->communicator->send(
            mailable: new FormConfirmation(
                $formRequest,
                $formType->replacePlaceholders($formRequest, (string) $formType->confirmation_subject),
                $formType->replacePlaceholders($formRequest, (string) $formType->confirmation_body, escapeHtml: true),
            ),
            to: $recipient,
            model: $formRequest,
        );

        return $recipient;
    }

    private function formTypeOf(FormRequest $formRequest): ?FormType
    {
        $formRequest->loadMissing('formType');

        return $formRequest->formType;
    }
}
