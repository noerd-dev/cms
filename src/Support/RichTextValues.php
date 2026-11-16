<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Support\HtmlSanitizer;

/**
 * Rich text fields hold HTML that element and collection templates print raw
 * (`{!! $element->text !!}`). Every such value goes through the allow-list
 * sanitizer — on the way OUT, here, centrally, for every field a YAML types as
 * rich text (`richText`, `translatableRichText`, any `…RichText`), so a row
 * written before sanitizing existed, by an import or straight into the
 * database cannot carry a script onto the public website. The save paths
 * sanitize as well (ContentDocumentService, CollectionEntryStore).
 *
 * A value is a string or a language map of strings; anything else is left
 * as it is.
 */
final class RichTextValues
{
    /**
     * Whether a YAML field type is a rich text type.
     */
    public static function isRichTextType(mixed $type): bool
    {
        return is_string($type) && str_ends_with(mb_strtolower($type), 'richtext');
    }

    /**
     * Sanitize the rich text fields an element declares in its YAML.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeElement(string $elementKey, array $data): array
    {
        $definition = FieldHelper::getElementFields($elementKey);

        if (! $definition) {
            return $data;
        }

        return self::sanitize($data, $definition['fields'] ?? []);
    }

    /**
     * Sanitize the rich text fields of a data array against a field list
     * (element YAML, collection definition, element-collection row schema).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function sanitize(array $data, array $fields): array
    {
        foreach (self::richTextKeys($fields) as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = self::sanitizeValue($data[$key]);
            }
        }

        return $data;
    }

    public static function sanitizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return HtmlSanitizer::sanitize($value);
        }

        if (is_array($value)) {
            foreach ($value as $code => $text) {
                if (is_string($text)) {
                    $value[$code] = HtmlSanitizer::sanitize($text);
                }
            }
        }

        return $value;
    }

    /**
     * The data keys of every rich text field, with the binding prefix
     * (`detailData.`) stripped.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return list<string>
     */
    public static function richTextKeys(array $fields): array
    {
        $keys = [];

        foreach (FieldHelper::flattenFields($fields) as $field) {
            if (self::isRichTextType($field['type'] ?? null) && isset($field['name'])) {
                $keys[] = (string) preg_replace('/^\w+\./', '', (string) $field['name']);
            }
        }

        return $keys;
    }
}
