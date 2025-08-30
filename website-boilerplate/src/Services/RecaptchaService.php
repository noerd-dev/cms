<?php

namespace Noerd\Website\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function __construct(
        private string $secretKey = '',
        private string $verifyUrl = '',
        private float $minimumScore = 0.5,
    ) {
        $this->secretKey = config('recaptcha.secret_key') ?? '';
        $this->verifyUrl = config('recaptcha.verify_url') ?? 'https://www.google.com/recaptcha/api/siteverify';
        $this->minimumScore = config('recaptcha.minimum_score') ?? 0.5;
    }

    /**
     * Verify the reCAPTCHA token
     */
    public function verify(string $token, ?string $remoteIp = null): bool
    {
        if (!$this->isEnabled()) {
            return true; // Allow submission if reCAPTCHA is not configured
        }

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->post($this->verifyUrl, [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp ?? request()->ip(),
            ]);

            $data = $response->json();

            if (!$data['success']) {
                Log::warning('reCAPTCHA verification failed', [
                    'error_codes' => $data['error-codes'] ?? [],
                    'token' => mb_substr($token, 0, 20) . '...',
                ]);
                return false;
            }

            // Check action (should be 'contact_form' for our form)
            if (isset($data['action']) && $data['action'] !== 'contact_form') {
                Log::warning('reCAPTCHA action mismatch', [
                    'expected' => 'contact_form',
                    'received' => $data['action'],
                ]);
                return false;
            }

            // Check score
            $score = $data['score'] ?? 0;
            if ($score < $this->minimumScore) {
                Log::warning('reCAPTCHA score too low', [
                    'score' => $score,
                    'minimum' => $this->minimumScore,
                ]);
                return false;
            }

            return true;

        } catch (Exception $e) {
            Log::error('reCAPTCHA verification error', [
                'message' => $e->getMessage(),
                'token' => mb_substr($token, 0, 20) . '...',
            ]);

            // Fail open in case of service issues
            return true;
        }
    }

    /**
     * Check if reCAPTCHA is enabled
     */
    public function isEnabled(): bool
    {
        return config('recaptcha.enabled', false);
    }

    /**
     * Get the site key for frontend usage
     */
    public function getSiteKey(): string
    {
        return config('recaptcha.site_key') ?? '';
    }
}
