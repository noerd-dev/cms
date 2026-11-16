<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

/**
 * The one rule for URLs that come from editor data and end up in a public
 * page's markup — `href`, `src`, a CSS `url()`, a navigation link.
 *
 * Allowed are absolute http(s) URLs, `mailto:` and `tel:`, fragments and
 * query strings (`#top`, `?page=2`) and site-relative paths (`/kontakt`,
 * `kontakt`). Everything else — `javascript:`, `data:`, `vbscript:`, any
 * other scheme and the protocol-relative `//evil.com` / `\\evil.com`, which
 * a browser reads as another host — is refused.
 *
 * Blade still escapes the value for the HTML attribute; this class only
 * decides whether the value may be a URL at all. Values printed into a CSS
 * `url()` additionally go through cssUrl(): Blade's escaping is undone by the
 * HTML parser before CSS sees the value, so quotes and parentheses have to be
 * encoded in the URL itself.
 */
final class SafeUrl
{
    /** @var list<string> */
    public const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * The value when it is a safe URL, the fallback otherwise.
     */
    public static function href(mixed $value, string $fallback = '#'): string
    {
        return self::isSafe($value) ? mb_trim((string) $value) : $fallback;
    }

    /**
     * The value when it is a safe URL, null otherwise — for optional links.
     */
    public static function orNull(mixed $value): ?string
    {
        return self::isSafe($value) ? mb_trim((string) $value) : null;
    }

    /**
     * A safe URL encoded for a CSS `url('…')`, or an empty string.
     */
    public static function cssUrl(mixed $value): string
    {
        if (! self::isSafe($value)) {
            return '';
        }

        return strtr(mb_trim((string) $value), [
            "'" => '%27',
            '"' => '%22',
            '(' => '%28',
            ')' => '%29',
            '\\' => '%5C',
            ' ' => '%20',
            "\n" => '',
            "\r" => '',
        ]);
    }

    public static function isSafe(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        // Browsers drop ASCII tab and newlines anywhere in a URL and control
        // characters and spaces around it (`java&#9;script:` runs as
        // javascript:), so the URL is judged without any of them.
        $url = (string) preg_replace('/[\x00-\x20\x7F]+/', '', (string) $value);

        if ($url === '') {
            return false;
        }

        // A backslash is a slash to the browser: `/\evil.com` and `\\evil.com`
        // leave the site like `//evil.com` does.
        $normalized = str_replace('\\', '/', $url);

        if (str_starts_with($normalized, '//')) {
            return false;
        }

        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $matches)) {
            // No scheme: a site-relative path, a fragment or a query string.
            return true;
        }

        return in_array(mb_strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
    }

    /**
     * Whether the value is a path on this site (`/kontakt`), never another host —
     * the rule for redirect targets.
     */
    public static function isSitePath(mixed $value): bool
    {
        if (! is_string($value) || ! str_starts_with($value, '/')) {
            return false;
        }

        return self::isSafe($value) && ! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $value);
    }
}
