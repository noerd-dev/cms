<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Noerd\Cms\Jobs\SendFormConfirmationEmail;
use Noerd\Cms\Mail\FormConfirmation;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class, RefreshDatabase::class);

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
        'email_subject' => 'Ihre Anfrage',
        'email_body' => '<p>Hallo {{field:name}}, danke fuer Ihre Nachricht.</p>',
    ]);
});

function submitContactForm(object $context, array $data = []): FormRequest
{
    return FormRequest::create([
        'form' => 'contact',
        'form_type_id' => $context->formType->id,
        'tenant_id' => $context->tenant->id,
        'data' => array_merge([
            'name' => 'Max Mustermann',
            'email' => 'absender@example.com',
            'nachricht' => 'Bitte um Rueckruf.',
        ], $data),
    ]);
}

it('stores the submission in the database', function (): void {
    $formRequest = submitContactForm($this);

    $this->assertDatabaseHas('form_requests', [
        'id' => $formRequest->id,
        'form' => 'contact',
        'tenant_id' => $this->tenant->id,
        'form_type_id' => $this->formType->id,
    ]);

    expect($formRequest->fresh()->data['email'])->toBe('absender@example.com');
});

it('sends a copy to the sender and to the recipients maintained in the cms settings', function (): void {
    CmsSetting::create([
        'tenant_id' => $this->tenant->id,
        'form_recipients' => 'empfaenger@example.com',
    ]);

    SendFormConfirmationEmail::dispatchSync(submitContactForm($this));

    Mail::assertSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('absender@example.com'));
    Mail::assertSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('empfaenger@example.com'));
    Mail::assertNotSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('fallback@example.com'));
});

it('sends to every recipient of a comma separated settings list', function (): void {
    CmsSetting::create([
        'tenant_id' => $this->tenant->id,
        'form_recipients' => 'eins@example.com, zwei@example.com',
    ]);

    SendFormConfirmationEmail::dispatchSync(submitContactForm($this));

    Mail::assertSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('eins@example.com') && $mail->hasTo('zwei@example.com'));
});

it('falls back to the form type notification email when no recipients are maintained', function (): void {
    SendFormConfirmationEmail::dispatchSync(submitContactForm($this));

    Mail::assertSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('fallback@example.com'));
    Mail::assertSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('absender@example.com'));
});

it('only notifies the recipients of the submitting tenant', function (): void {
    $otherTenant = Tenant::factory()->create();

    CmsSetting::create(['tenant_id' => $this->tenant->id, 'form_recipients' => 'richtig@example.com']);
    CmsSetting::create(['tenant_id' => $otherTenant->id, 'form_recipients' => 'falsch@example.com']);

    SendFormConfirmationEmail::dispatchSync(submitContactForm($this));

    Mail::assertSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('richtig@example.com'));
    Mail::assertNotSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('falsch@example.com'));
});

it('skips the sender copy when no valid email was submitted', function (): void {
    CmsSetting::create([
        'tenant_id' => $this->tenant->id,
        'form_recipients' => 'empfaenger@example.com',
    ]);

    SendFormConfirmationEmail::dispatchSync(submitContactForm($this, ['email' => 'keine-mail']));

    Mail::assertSent(FormConfirmation::class, 1);
    Mail::assertSent(FormConfirmation::class, fn ($mail): bool => $mail->hasTo('empfaenger@example.com'));
});
