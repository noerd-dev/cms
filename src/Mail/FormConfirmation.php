<?php

declare(strict_types=1);

namespace Noerd\Cms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Noerd\Cms\Models\FormRequest;

class FormConfirmation extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public FormRequest $formRequest,
        public string $emailSubject,
        public string $emailBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'cms::emails.form-confirmation',
            with: [
                'formRequest' => $this->formRequest,
                'emailBody' => $this->emailBody,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
