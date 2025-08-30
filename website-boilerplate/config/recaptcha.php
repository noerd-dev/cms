<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA v3 Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google reCAPTCHA v3 integration in CMS Frontend.
    |
    | Add these to your .env file:
    | RECAPTCHA_SITE_KEY=your-site-key-here
    | RECAPTCHA_SECRET_KEY=your-secret-key-here
    |
    */

    'site_key' => env('RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
    'minimum_score' => 0.5, // Minimum score threshold (0.0 to 1.0)
    'enabled' => env('RECAPTCHA_SITE_KEY') && env('RECAPTCHA_SECRET_KEY'),
];
