<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * The meta data a website layout prints into `<head>` for a page: title,
 * description, robots and the Open Graph image — one place for both website
 * copies (the boilerplate and a project's installed copy).
 *
 * `meta_title` / `meta_description` are language maps (a plain string of an
 * older row is taken as it is), the title falls back to the page name.
 * `og_image` holds a media id and is resolved to an ABSOLUTE delivery URL
 * (crawlers fetch it from another host); a legacy URL string is kept when it
 * is a safe URL.
 */
final class PageMeta
{
    /**
     * @return array{title: ?string, description: ?string, noindex: bool, image: ?string}
     */
    public static function of(?Model $page, string $language): array
    {
        if ($page === null) {
            return ['title' => null, 'description' => null, 'noindex' => false, 'image' => null];
        }

        $title = self::localized($page->getAttribute('meta_title'), $language)
            ?? self::localized(self::decode($page->getAttribute('name')), $language);

        return [
            'title' => $title,
            'description' => self::localized($page->getAttribute('meta_description'), $language),
            'noindex' => (bool) $page->getAttribute('meta_noindex'),
            'image' => self::imageUrl($page->getAttribute('og_image')),
        ];
    }

    /**
     * The absolute URL of an image field value (media id or legacy URL), or null.
     */
    public static function imageUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $url = MediaValues::url($value) ?? (MediaValues::isReference($value) ? null : SafeUrl::orNull($value));

        if ($url === null) {
            return null;
        }

        return preg_match('/^https?:/i', $url) ? $url : url($url);
    }

    private static function localized(mixed $value, string $language): ?string
    {
        $value = self::decode($value);

        if (is_array($value)) {
            $value = $value[$language] ?? null;
        }

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }

    private static function decode(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with(mb_ltrim($value), '{')) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }

        return $value;
    }
}
