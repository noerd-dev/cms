<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Noerd\Cms\Models\CmsSetting;

/**
 * The ONE gate for the website's cookie banner and Google tag (CmsSetting:
 * `show_cookie_banner`, `cookie_lifetime_days`, `google_analytics_id`), edited on
 * `cms::cms-settings-page` and rendered by the website layout.
 *
 * - the banner shows while `show_cookie_banner` is on; acceptance and rejection
 *   are stored for the same duration (CmsSetting::cookieLifetimeInDays())
 * - the Google tag exists only TOGETHER with the banner and loads only after
 *   the visitor's consent (the layout's loader, called by the banner)
 * - the ID must match CmsSetting::GOOGLE_TAG_PATTERN (checked on save as well)
 * - it only runs on a host that shares no session with the backend: a Google
 *   Tag Manager container can inject any JavaScript, and on the backend's own
 *   origin it would run with the session of whoever opens the website — a
 *   signed-in admin included
 */
final class WebsiteTrackingService
{
    /**
     * What the website layout renders for a request of a tenant on `$host`.
     *
     * @return array{showCookieBanner: bool, cookieLifetimeDays: int, googleTagId: ?string}
     */
    public function forRequest(int $tenantId, string $host): array
    {
        $settings = CmsSetting::forTenant($tenantId);
        $showCookieBanner = (bool) $settings->show_cookie_banner;
        $googleTagId = (string) $settings->google_analytics_id;

        $runsTag = $showCookieBanner
            && preg_match(CmsSetting::GOOGLE_TAG_PATTERN, $googleTagId) === 1
            && $this->isIsolatedFromBackend($host);

        return [
            'showCookieBanner' => $showCookieBanner,
            'cookieLifetimeDays' => $settings->cookieLifetimeInDays(),
            'googleTagId' => $runsTag ? $googleTagId : null,
        ];
    }

    /**
     * Whether `$host` shares no session cookie with the backend: neither the
     * backend's own host (`app.url`) nor a host the session cookie domain
     * covers.
     */
    public function isIsolatedFromBackend(string $host): bool
    {
        $host = $this->normalize($host);

        if ($host === '') {
            return false;
        }

        $backendHost = $this->normalize((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === $backendHost) {
            return false;
        }

        $cookieDomain = mb_ltrim($this->normalize((string) config('session.domain')), '.');

        return $cookieDomain === '' || ($host !== $cookieDomain && ! str_ends_with($host, '.' . $cookieDomain));
    }

    private function normalize(string $host): string
    {
        $host = mb_strtolower(mb_trim($host));

        return str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;
    }
}
