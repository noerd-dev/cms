<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Noerd\Cms\Jobs\SendFormConfirmationEmail;
use Noerd\Cms\Jobs\SendFormNotificationEmail;
use Noerd\Cms\Models\FormType;
use Noerd\Website\Models\FormRequest;
use Noerd\Website\Models\Tenant;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/*
 | The contact form submits through the CMS (FormSubmissionService): the form
 | YAML of the `contact` form type decides fields, rules and mails.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    session(['selectedTenantId' => $this->tenant->id, 'uuid' => $this->tenant->uuid]);

    $directory = storage_path('framework/testing/zz-website-contact');
    File::ensureDirectoryExists($directory);
    File::put($directory . '/contact.yml', implode("\n", [
        'key: contact',
        'title: Contact',
        'fields:',
        '  - name: name',
        '    required: true',
        '  - name: email',
        '    required: true',
        '    validation:',
        '      - email',
        '  - name: phone',
        '  - name: message',
        '    required: true',
    ]));
    $this->formType = FormType::factory()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'contact',
        'yml_path' => $directory . '/contact.yml',
    ]);
});

it('stores a valid submission and queues the mails of the form type', function (): void {
    Queue::fake();
    $this->formType->update(['send_email' => true, 'send_confirmation' => true]);

    Livewire::test('website::contact-form')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('message', 'Hello')
        ->call('submit')
        ->assertSet('showSuccess', true);

    expect(FormRequest::query()->ofTenant($this->tenant->id)->first()->data['email'])->toBe('john@example.com');
    Queue::assertPushed(SendFormNotificationEmail::class, 1);
    Queue::assertPushed(SendFormConfirmationEmail::class, 1);
});

it('reports the rules of the form YAML on the fields', function (): void {
    Livewire::test('website::contact-form')
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'message']);
});

it('refuses the submission while the form type is missing (fail-closed)', function (): void {
    $this->formType->delete();

    Livewire::test('website::contact-form')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('message', 'Hello')
        ->call('submit')
        ->assertSet('showSuccess', false)
        ->assertSet('errorMessage', __('The form is not available at the moment. Please try again later.'));

    expect(FormRequest::query()->ofTenant($this->tenant->id)->count())->toBe(0);
});
