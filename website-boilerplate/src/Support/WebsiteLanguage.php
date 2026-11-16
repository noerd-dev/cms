<?php

declare(strict_types=1);

namespace Noerd\Website\Support;

use Noerd\Cms\Support\CmsLanguageCodes;

/**
 * The language a website VISITOR reads in. It has a session key of its own:
 * the backend's language (`selectedLanguage`, SetupLanguage) belongs to the
 * signed-in user, and a visit of the website on the same domain must never
 * change it — nor the other way round.
 */
final class WebsiteLanguage
{
    public const SESSION_KEY = 'website.language';

    /**
     * The visitor's language: the session value, else the tenant's default
     * website language (WebsiteMiddleware keeps the session value valid).
     */
    public static function current(): string
    {
        $language = session(self::SESSION_KEY);

        return is_string($language) && $language !== '' ? $language : (CmsLanguageCodes::active()[0] ?? CmsLanguageCodes::FALLBACK[0]);
    }

    public static function set(string $code): void
    {
        session([self::SESSION_KEY => $code]);
    }
}
