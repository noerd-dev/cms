<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Noerd\Website\Models\FormRequest;
use Noerd\Website\Models\Tenant;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        // Set default reCAPTCHA configuration for testing
        config([
            'recaptcha.enabled' => false,
            'recaptcha.site_key' => '',
            'recaptcha.secret_key' => '',
            'recaptcha.verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
            'recaptcha.minimum_score' => 0.5,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_contact_form_component(): void
    {
        $component = Volt::test('contact-form');

        $component->assertSee('Kontakt');
        $component->assertSee('Name');
        $component->assertSee('E-Mail');
        $component->assertSee('Telefon');
        $component->assertSee('Nachricht');
        $component->assertSee('Nachricht senden');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_submit_contact_form_via_livewire(): void
    {
        // Simulate middleware setting session data
        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '+49 123 456789')
            ->set('message', 'This is a test message')
            ->call('submit');

        $component->assertSet('showSuccess', true)
            ->assertSet('errorMessage', '');
        $component->assertSet('name', '');
        $component->assertSet('email', '');
        $component->assertSet('phone', '');
        $component->assertSet('message', '');

        $this->assertDatabaseHas('form_requests', [
            'form' => 'contact',
            'tenant_id' => $this->tenant->id,
        ]);

        $formRequest = FormRequest::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals('John Doe', $formRequest->data['name']);
        $this->assertEquals('john@example.com', $formRequest->data['email']);
        $this->assertEquals('+49 123 456789', $formRequest->data['phone']);
        $this->assertEquals('This is a test message', $formRequest->data['message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_required_fields(): void
    {
        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        $component = Volt::test('contact-form')
            ->set('name', '')
            ->set('email', '')
            ->set('message', '')
            ->call('submit');

        $component->assertHasErrors(['name', 'email', 'message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_email_format(): void
    {
        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'invalid-email')
            ->set('message', 'Test message')
            ->call('submit');

        $component->assertHasErrors(['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_tenant_context(): void
    {
        // Don't set tenant_id to simulate missing tenant context
        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'Test message')
            ->call('submit');

        $component->assertSet('errorMessage', 'Fehler: Mandanten-Information fehlt. Bitte laden Sie die Seite neu.');
        $this->assertDatabaseMissing('form_requests', [
            'form' => 'contact',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function phone_field_is_optional(): void
    {
        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'Test message without phone')
            ->call('submit');

        $component->assertSet('showSuccess', true);

        $formRequest = FormRequest::where('tenant_id', $this->tenant->id)->first();
        $this->assertEmpty($formRequest->data['phone']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_recaptcha_when_enabled(): void
    {
        // Enable reCAPTCHA for this test
        config([
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => 'test-site-key',
            'recaptcha.secret_key' => 'test-secret-key',
        ]);

        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        // Mock HTTP response for failed reCAPTCHA validation
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'score' => 0.1,
                'action' => 'contact_form',
            ]),
        ]);

        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'Test message')
            ->set('recaptchaToken', 'invalid-token')
            ->call('submit');

        $component->assertSet('errorMessage', 'reCAPTCHA-Verifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_submission_when_recaptcha_disabled(): void
    {
        // Explicitly disable reCAPTCHA
        config(['recaptcha.enabled' => false]);

        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'Test message')
            ->call('submit');

        $component->assertSet('showSuccess', true);
        $this->assertDatabaseHas('form_requests', [
            'form' => 'contact',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_submits_successfully_with_valid_recaptcha_token(): void
    {
        // Enable reCAPTCHA but mock successful verification
        config([
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => 'test-site-key',
            'recaptcha.secret_key' => 'test-secret-key',
        ]);

        // Mock HTTP response for reCAPTCHA verification
        \Illuminate\Support\Facades\Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'contact_form',
            ], 200),
        ]);

        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'Test message')
            ->set('recaptchaToken', 'valid-test-token')
            ->call('submit');

        $component->assertSet('showSuccess', true);
        $this->assertDatabaseHas('form_requests', [
            'form' => 'contact',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_recaptcha_token(): void
    {
        // Enable reCAPTCHA and mock failed verification
        config([
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => 'test-site-key',
            'recaptcha.secret_key' => 'test-secret-key',
        ]);

        // Mock HTTP response for failed reCAPTCHA verification
        \Illuminate\Support\Facades\Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => \Illuminate\Support\Facades\Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'Test message')
            ->set('recaptchaToken', 'invalid-test-token')
            ->call('submit');

        $component->assertSet('errorMessage', 'reCAPTCHA-Verifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
        $this->assertDatabaseMissing('form_requests', [
            'form' => 'contact',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_low_score_recaptcha(): void
    {
        // Enable reCAPTCHA and mock low score response
        config([
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => 'test-site-key',
            'recaptcha.secret_key' => 'test-secret-key',
            'recaptcha.minimum_score' => 0.5,
        ]);

        // Mock HTTP response with low score
        \Illuminate\Support\Facades\Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'score' => 0.2, // Below minimum threshold
                'action' => 'contact_form',
            ], 200),
        ]);

        session(['selectedTenantId' => $this->tenant->id, 'hash' => $this->tenant->hash]);

        $component = Volt::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('message', 'Test message')
            ->set('recaptchaToken', 'low-score-token')
            ->call('submit');

        $component->assertSet('errorMessage', 'reCAPTCHA-Verifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
        $this->assertDatabaseMissing('form_requests', [
            'form' => 'contact',
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
