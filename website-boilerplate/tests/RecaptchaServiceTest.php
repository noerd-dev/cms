<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Noerd\Website\Services\RecaptchaService;
use Tests\TestCase;

class RecaptchaServiceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_true_when_recaptcha_is_disabled(): void
    {
        config(['recaptcha.enabled' => false]);

        $service = new RecaptchaService();

        $this->assertFalse($service->isEnabled());
        $this->assertTrue($service->verify('any-token'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_verifies_valid_recaptcha_token(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => 'test-site-key',
            'recaptcha.secret_key' => 'test-secret-key',
            'recaptcha.minimum_score' => 0.5,
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'contact_form',
            ], 200),
        ]);

        $service = new RecaptchaService();

        $this->assertTrue($service->isEnabled());
        $this->assertTrue($service->verify('valid-token'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_recaptcha_token(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.secret_key' => 'test-secret-key',
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        $service = new RecaptchaService();

        $this->assertFalse($service->verify('invalid-token'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_low_score_recaptcha(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.secret_key' => 'test-secret-key',
            'recaptcha.minimum_score' => 0.5,
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.2, // Below threshold
                'action' => 'contact_form',
            ], 200),
        ]);

        $service = new RecaptchaService();

        $this->assertFalse($service->verify('low-score-token'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_wrong_action(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.secret_key' => 'test-secret-key',
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'wrong_action',
            ], 200),
        ]);

        $service = new RecaptchaService();

        $this->assertFalse($service->verify('wrong-action-token'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_fails_open_on_http_error(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.secret_key' => 'test-secret-key',
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response('Server Error', 500),
        ]);

        $service = new RecaptchaService();

        // Should fail open (return true) on service errors
        $this->assertTrue($service->verify('any-token'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_false_for_empty_token(): void
    {
        config([
            'recaptcha.enabled' => true,
            'recaptcha.secret_key' => 'test-secret-key',
        ]);

        $service = new RecaptchaService();

        $this->assertFalse($service->verify(''));
        $this->assertFalse($service->verify(' '));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_correct_site_key(): void
    {
        config(['recaptcha.site_key' => 'test-site-key-123']);

        $service = new RecaptchaService();

        $this->assertEquals('test-site-key-123', $service->getSiteKey());
    }
}
