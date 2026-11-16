<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Noerd\Cms\Jobs\SendFormConfirmationEmail;
use Noerd\Cms\Jobs\SendFormNotificationEmail;
use Noerd\Cms\Mail\FormConfirmation;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Helpers\FormatHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

/*
 | The two mails of a submission (FormMailer): the NOTIFICATION goes to the
 | form recipients only, the CONFIRMATION to the visitor only — each with its
 | own switch, subject and body.
 */
beforeEach(function (): void {
    Mail::fake();

    $this->tenant = Tenant::factory()->create();

    $this->formType = FormType::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'contact',
        'title' => 'Kontaktformular',
        'yml_path' => 'forms/contact.yml',
        'send_email' => true,
        'notification_email' => 'fallback@example.com',
        'email_subject' => 'New request',
        'email_body' => '<p>New request from {{field:name}}.</p>',
        'send_confirmation' => true,
        'confirmation_subject' => 'Ihre Anfrage',
        'confirmation_body' => '<p>Hello {{field:name}}, thanks for your message.</p>',
    ]);
});

function zzSubmitContactForm(object $context, array $data = []): FormRequest
{
    return FormRequest::create([
        'form' => 'contact',
        'form_type_id' => $context->formType->id,
        'tenant_id' => $context->tenant->id,
        'data' => array_merge([
            'name' => 'Max Mustermann',
            'email' => 'absender@example.com',
            'message' => 'Please call back.',
        ], $data),
    ]);
}

describe('notification', function (): void {
    it('goes to the recipients of the cms settings and never to the sender', function (): void {
        CmsSetting::create(['tenant_id' => $this->tenant->id, 'form_recipients' => 'empfaenger@example.com']);

        SendFormNotificationEmail::dispatchSync(zzSubmitContactForm($this));

        Mail::assertSent(FormConfirmation::class, 1);
        Mail::assertSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('empfaenger@example.com')
            && $mail->emailSubject === 'New request');
        Mail::assertNotSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('absender@example.com'));
        Mail::assertNotSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('fallback@example.com'));
    });

    it('sends to every recipient of a comma separated settings list', function (): void {
        CmsSetting::create(['tenant_id' => $this->tenant->id, 'form_recipients' => 'eins@example.com, zwei@example.com']);

        SendFormNotificationEmail::dispatchSync(zzSubmitContactForm($this));

        Mail::assertSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('eins@example.com') && $mail->hasTo('zwei@example.com'));
    });

    it('falls back to the form type notification email when no recipients are maintained', function (): void {
        SendFormNotificationEmail::dispatchSync(zzSubmitContactForm($this));

        Mail::assertSent(FormConfirmation::class, 1);
        Mail::assertSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('fallback@example.com'));
    });

    it('only notifies the recipients of the submitting tenant', function (): void {
        $otherTenant = Tenant::factory()->create();

        CmsSetting::create(['tenant_id' => $this->tenant->id, 'form_recipients' => 'richtig@example.com']);
        CmsSetting::create(['tenant_id' => $otherTenant->id, 'form_recipients' => 'falsch@example.com']);

        SendFormNotificationEmail::dispatchSync(zzSubmitContactForm($this));

        Mail::assertSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('richtig@example.com'));
        Mail::assertNotSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('falsch@example.com'));
    });

    it('is not sent while switched off', function (): void {
        $this->formType->update(['send_email' => false]);

        SendFormNotificationEmail::dispatchSync(zzSubmitContactForm($this));

        Mail::assertNothingSent();
    });
});

describe('confirmation', function (): void {
    it('goes to the sender only, with its own subject and body', function (): void {
        CmsSetting::create(['tenant_id' => $this->tenant->id, 'form_recipients' => 'empfaenger@example.com']);

        SendFormConfirmationEmail::dispatchSync(zzSubmitContactForm($this));

        Mail::assertSent(FormConfirmation::class, 1);
        Mail::assertSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('absender@example.com')
            && $mail->emailSubject === 'Ihre Anfrage'
            && $mail->emailBody === '<p>Hello Max Mustermann, thanks for your message.</p>');
        Mail::assertNotSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('empfaenger@example.com'));
    });

    it('reads the sender address from the configured field', function (): void {
        $this->formType->update(['confirmation_field' => 'reply_to']);

        SendFormConfirmationEmail::dispatchSync(zzSubmitContactForm($this, ['reply_to' => 'antwort@example.com']));

        Mail::assertSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('antwort@example.com'));
        Mail::assertNotSent(FormConfirmation::class, fn($mail): bool => $mail->hasTo('absender@example.com'));
    });

    it('is skipped when no valid address was submitted', function (): void {
        SendFormConfirmationEmail::dispatchSync(zzSubmitContactForm($this, ['email' => 'keine-mail']));

        Mail::assertNothingSent();
    });

    it('is not sent while switched off or without text', function (array $overrides): void {
        $this->formType->update($overrides);

        SendFormConfirmationEmail::dispatchSync(zzSubmitContactForm($this));

        Mail::assertNothingSent();
    })->with([
        'switched off' => [['send_confirmation' => false]],
        'no subject' => [['confirmation_subject' => null]],
        'no body' => [['confirmation_body' => null]],
    ]);
});

describe('placeholders', function (): void {
    it('substitutes the form title, the submission date and every field placeholder', function (): void {
        $this->formType->update([
            'confirmation_subject' => 'Re: {{form_title}}',
            'confirmation_body' => '<p>Hello {{field:name}} ({{field:email}}), eingegangen am {{submission_date}}.</p>',
        ]);

        $formRequest = zzSubmitContactForm($this);

        SendFormConfirmationEmail::dispatchSync($formRequest);

        Mail::assertSent(FormConfirmation::class, fn(FormConfirmation $mail): bool => $mail->emailSubject === 'Re: Kontaktformular'
                && $mail->emailBody === '<p>Hello Max Mustermann (absender@example.com), eingegangen am '
                    . FormatHelper::documentDateTime($formRequest->created_at, $this->tenant->id) . '.</p>');
    });

    it('writes the submission date in the tenant locale', function (): void {
        // The mail leaves the system, so the tenant locale applies — whatever
        // the format of the user who happens to be logged in.
        NoerdSettings::create(['tenant_id' => $this->tenant->id, 'currency' => 'USD', 'locale' => 'en-US']);

        $this->formType->update(['email_body' => '<p>Eingegangen am {{submission_date}}.</p>']);

        $formRequest = zzSubmitContactForm($this);

        SendFormNotificationEmail::dispatchSync($formRequest);

        Mail::assertSent(FormConfirmation::class, fn(FormConfirmation $mail): bool => str_contains($mail->emailBody, $formRequest->created_at->format('m/d/Y'))
                && ! str_contains($mail->emailBody, $formRequest->created_at->format('d.m.Y')));
    });

    it('escapes submitted values before they reach the html mail body', function (): void {
        SendFormConfirmationEmail::dispatchSync(
            zzSubmitContactForm($this, ['name' => '<script>alert(1)</script>']),
        );

        Mail::assertSent(FormConfirmation::class, fn(FormConfirmation $mail): bool => ! str_contains($mail->emailBody, '<script>')
                && str_contains($mail->emailBody, '&lt;script&gt;'));
    });

    it('renders the substituted body into the mail view', function (): void {
        $mail = new FormConfirmation(
            zzSubmitContactForm($this, ['name' => 'Erika']),
            'Ihre Anfrage',
            '<p>Hello Erika</p>',
        );

        expect($mail->render())->toContain('<p>Hello Erika</p>');
    });
});
