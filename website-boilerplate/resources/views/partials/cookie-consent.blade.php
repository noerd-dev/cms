{{--
 | The cookie banner of devrabiul/laravel-cookie-consent, switched on per tenant in the
 | CMS settings (WebsiteTrackingService). Texts are passed per request, so they follow
 | the visitor's language. Acceptance and rejection are stored for the same duration on
 | purpose (CmsSetting::cookieLifetimeInDays()). The analytics category loads the Google
 | tag only when the tenant configured one.
--}}
@if ($tracking['showCookieBanner'] ?? false)
    {!! \Devrabiul\CookieConsent\Facades\CookieConsent::scripts([
        'enabled' => true,
        'cookie_prefix' => 'website',
        'cookie_lifetime' => $tracking['cookieLifetimeDays'],
        'reject_lifetime' => $tracking['cookieLifetimeDays'],
        'consent_modal_layout' => 'bar',
        'preferences_modal_enabled' => true,
        'preferences_modal_layout' => 'bar',
        'disable_page_interaction' => false,
        'cookie_title' => __('Cookie Notice'),
        'cookie_description' => __('We use cookies to analyse how our website is used and to improve it. Analytics cookies are only set with your consent.'),
        'cookie_accept_btn_text' => __('Accept all'),
        'cookie_reject_btn_text' => __('Necessary only'),
        'cookie_preferences_btn_text' => __('Manage settings'),
        'cookie_preferences_save_text' => __('Save settings'),
        'cookie_modal_title' => __('Cookie Settings'),
        'cookie_modal_intro' => __('Here you can choose which cookies you allow.'),
        'cookie_categories' => array_filter([
            'necessary' => [
                'enabled' => true,
                'locked' => true,
                'title' => __('Necessary cookies'),
                'description' => __('These cookies are required for the website to work.'),
            ],
            'analytics' => ($tracking['googleTagId'] ?? null) ? [
                'enabled' => true,
                'locked' => false,
                'js_action' => 'loadWebsiteGoogleTag',
                'title' => __('Analytics cookies'),
                'description' => __('These cookies help us understand how visitors use our website.'),
            ] : null,
        ]),
        'policy_links' => [],
    ]) !!}
@endif
