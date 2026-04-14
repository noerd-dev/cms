<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Noerd\Cms\Mail\FormConfirmation;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    $cmsApp = TenantApp::firstOrCreate(
        ['name' => 'CMS'],
        [
            'title' => 'CMS',
            'icon' => 'cms::icons.app',
            'route' => 'cms.index',
            'is_active' => true,
        ],
    );
    $this->tenant->tenantApps()->attach($cmsApp->id);

    $this->user = NoerdUser::factory()->create();
    $this->user->tenants()->attach($this->tenant->id);
    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('CMS');
});

function createFormType(object $context, array $overrides = []): FormType
{
    return FormType::create(array_merge([
        'tenant_id' => $context->tenant->id,
        'key' => 'contact-form',
        'title' => 'Kontaktformular',
        'yml_path' => 'forms/contact.yml',
        'send_email' => true,
        'email_subject' => 'Neue Anfrage',
        'email_body' => 'Es gibt eine neue Anfrage.',
        'notification_email' => 'admin@example.com',
    ], $overrides));
}

it('renders the form-request-detail component', function (): void {
    $formRequest = FormRequest::create([
        'tenant_id' => $this->tenant->id,
        'form' => 'contact',
        'data' => json_encode(['name' => 'Test User', 'email' => 'test@example.com']),
    ]);

    Livewire::actingAs($this->user)
        ->test('form-request-detail', ['modelId' => $formRequest->id])
        ->assertSee('Form Request')
        ->assertSee('Test User')
        ->assertSee('test@example.com');
});

it('shows resend button when form type has notification email configured', function (): void {
    $formType = createFormType($this);

    $formRequest = FormRequest::create([
        'tenant_id' => $this->tenant->id,
        'form_type_id' => $formType->id,
        'form' => 'contact',
        'data' => json_encode(['name' => 'Test']),
    ]);

    Livewire::actingAs($this->user)
        ->test('form-request-detail', ['modelId' => $formRequest->id])
        ->assertSee('Benachrichtigung erneut senden');
});

it('hides resend button when no form type is assigned', function (): void {
    $formRequest = FormRequest::create([
        'tenant_id' => $this->tenant->id,
        'form_type_id' => null,
        'form' => 'contact',
        'data' => json_encode(['name' => 'Test']),
    ]);

    Livewire::actingAs($this->user)
        ->test('form-request-detail', ['modelId' => $formRequest->id])
        ->assertDontSee('Benachrichtigung erneut senden');
});

it('hides resend button when notification email is empty', function (): void {
    $formType = createFormType($this, ['notification_email' => null]);

    $formRequest = FormRequest::create([
        'tenant_id' => $this->tenant->id,
        'form_type_id' => $formType->id,
        'form' => 'contact',
        'data' => json_encode(['name' => 'Test']),
    ]);

    Livewire::actingAs($this->user)
        ->test('form-request-detail', ['modelId' => $formRequest->id])
        ->assertDontSee('Benachrichtigung erneut senden');
});

it('hides resend button when send_email is disabled', function (): void {
    $formType = createFormType($this, ['send_email' => false]);

    $formRequest = FormRequest::create([
        'tenant_id' => $this->tenant->id,
        'form_type_id' => $formType->id,
        'form' => 'contact',
        'data' => json_encode(['name' => 'Test']),
    ]);

    Livewire::actingAs($this->user)
        ->test('form-request-detail', ['modelId' => $formRequest->id])
        ->assertDontSee('Benachrichtigung erneut senden');
});

it('sends notification email only to the notification address', function (): void {
    Mail::fake();

    $formType = createFormType($this, [
        'email_subject' => 'New: {{form_title}}',
        'email_body' => 'Submission from {{field:name}}',
    ]);

    $formRequest = FormRequest::create([
        'tenant_id' => $this->tenant->id,
        'form_type_id' => $formType->id,
        'form' => 'contact',
        'data' => json_encode(['name' => 'Max Mustermann', 'email' => 'customer@example.com']),
    ]);

    Livewire::actingAs($this->user)
        ->test('form-request-detail', ['modelId' => $formRequest->id])
        ->call('resendNotificationEmail');

    Mail::assertSent(FormConfirmation::class, fn(FormConfirmation $mail) => $mail->hasTo('admin@example.com')
            && ! $mail->hasTo('customer@example.com'));

    Mail::assertSent(FormConfirmation::class, 1);
});

it('prevents rapid resending via rate limiting', function (): void {
    Mail::fake();

    $formType = createFormType($this);

    $formRequest = FormRequest::create([
        'tenant_id' => $this->tenant->id,
        'form_type_id' => $formType->id,
        'form' => 'contact',
        'data' => json_encode(['name' => 'Test']),
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('form-request-detail', ['modelId' => $formRequest->id]);

    // First call should succeed
    $component->call('resendNotificationEmail');
    Mail::assertSent(FormConfirmation::class, 1);

    // Second call should be rate limited
    $component->call('resendNotificationEmail');
    Mail::assertSent(FormConfirmation::class, 1);
});

it('includes form_type_id in detailData', function (): void {
    $formType = createFormType($this);

    $formRequest = FormRequest::create([
        'tenant_id' => $this->tenant->id,
        'form_type_id' => $formType->id,
        'form' => 'contact',
        'data' => json_encode(['name' => 'Test']),
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('form-request-detail', ['modelId' => $formRequest->id]);

    expect($component->get('detailData.form_type_id'))->toBe($formType->id);
});
