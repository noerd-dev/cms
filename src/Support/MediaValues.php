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
 * The URL is the DELIVERY URL of the image (`getImageUrl()`): a size-limited
 * variant, never the oversized original. An image field may name another
 * variant of the media configuration with `variant:` in its YAML; `web` is the
 * default.
 *
 * Values that are not an id (a legacy `/storage/...` string, an external URL)
 * are passed through untouched.
 */
final class MediaValues
{
    /**
     * The variant an image field delivers unless its YAML names another one.
     */
    public const DEFAULT_VARIANT = 'web';

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
        foreach (self::imageKeys($fields) as $key => $variant) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $url = self::url($data[$key], $variant);

            if ($url !== null) {
                $data[$key] = $url;
            }
        }

        return $data;
    }

    /**
     * The delivery URL of a stored media reference, or null when the value is
     * not one.
     */
    public static function url(mixed $value, string $variant = self::DEFAULT_VARIANT): ?string
    {
        if (! self::isReference($value)) {
            return null;
        }

        return app(MediaResolverContract::class)->getImageUrl((int) $value, $variant);
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
     * prefix (`detailData.`) stripped — that is how the values are stored —,
     * each with the variant the field delivers.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, string>
     */
    private static function imageKeys(array $fields): array
    {
        $keys = [];

        foreach (FieldHelper::flattenFields($fields) as $field) {
            if (($field['type'] ?? null) !== 'image' || ! isset($field['name'])) {
                continue;
            }

            $key = (string) preg_replace('/^\w+\./', '', (string) $field['name']);
            $variant = $field['variant'] ?? null;

            $keys[$key] = is_string($variant) && $variant !== '' ? $variant : self::DEFAULT_VARIANT;
        }

        return $keys;
    }
}
