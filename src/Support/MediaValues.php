<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Contracts\MediaResolverContract;

/**
 * Image fields store the MEDIA ID, not a URL.
 *
 * The media disk mirrors the folder tree, so a file's path changes when it is
 * moved in the library. A URL written into `cms_pages.data` would then point
 * nowhere. The id is stable, and the URL is resolved on the way out — here,
 * centrally, so that every element Blade keeps reading a plain URL and no
 * project's element templates have to change.
 *
 * Values that are not an id (a legacy `/storage/...` string, an external URL)
 * are passed through untouched.
 */
final class MediaValues
{
    /**
     * Resolve the image fields an element declares in its YAML.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolveElement(string $elementKey, array $data): array
    {
        $definition = FieldHelper::getElementFields($elementKey);

        if (! $definition) {
            return $data;
        }

        return self::resolve($data, $definition['fields'] ?? []);
    }

    /**
     * Resolve the image fields of a data array against a field definition list
     * (element YAML or collection definition).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function resolve(array $data, array $fields): array
    {
        foreach (self::imageKeys($fields) as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $url = self::url($data[$key]);

            if ($url !== null) {
                $data[$key] = $url;
            }
        }

        return $data;
    }

    /**
     * The URL of a stored media reference, or null when the value is not one.
     */
    public static function url(mixed $value): ?string
    {
        if (! self::isReference($value)) {
            return null;
        }

        return app(MediaResolverContract::class)->getRelativeUrl((int) $value);
    }

    /**
     * A media reference is a bare positive integer. Anything else — an empty
     * value, a path, a full URL — is content of its own.
     */
    public static function isReference(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        return is_string($value) && $value !== '' && ctype_digit($value) && (int) $value > 0;
    }

    /**
     * The data keys of every field declared as an image, with the binding
     * prefix (`detailData.`) stripped — that is how the values are stored.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    private static function imageKeys(array $fields): array
    {
        $keys = [];

        foreach (FieldHelper::flattenFields($fields) as $field) {
            if (($field['type'] ?? null) !== 'image' || ! isset($field['name'])) {
                continue;
            }

            $keys[] = (string) preg_replace('/^\w+\./', '', (string) $field['name']);
        }

        return $keys;
    }
}
