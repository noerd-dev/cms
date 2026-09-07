<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\FormType;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->formType = FormType::factory()->create([
        'tenant_id' => $this->tenantId,
        'key' => 'contact',
        'title' => 'Contact',
        'send_email' => false,
    ]);
});

it('stores the email settings of a form type', function (): void {
    Livewire::test('cms::form-type-detail', ['modelId' => $this->formType->id])
        ->set('detailData.send_email', true)
        ->set('detailData.email_subject', 'New request')
        ->set('detailData.notification_email', 'forms@example.com')
        ->call('store')
        ->assertHasNoErrors();

    $fresh = $this->formType->fresh();
    expect($fresh->send_email)->toBeTrue()
        ->and($fresh->email_subject)->toBe('New request')
        ->and($fresh->notification_email)->toBe('forms@example.com');
});

it('rejects an invalid notification email', function (): void {
    Livewire::test('cms::form-type-detail', ['modelId' => $this->formType->id])
        ->set('detailData.notification_email', 'not-an-email')
        ->call('store')
        ->assertHasErrors(['detailData.notification_email']);
});

it('never loads a form type of another tenant', function (): void {
    $foreign = FormType::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'key' => 'secret',
        'title' => 'Secret form',
    ]);

    Livewire::test('cms::form-type-detail', ['modelId' => $foreign->id])
        ->assertSet('detailData.key', null)
        ->assertDontSee('Secret form');
});
