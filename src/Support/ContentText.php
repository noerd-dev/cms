<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;

/**
 * The readable text of a content as Markdown: the name as a heading, then the
 * text fields of every element in order. Which fields are text comes from the
 * element YAML, so any element — of any module — is read without knowing it.
 *
 * Translatable values are read in the tenant's default language, falling back
 * to the first filled one. Rich text keeps its headings (h1–h3 → `#`), so a
 * chunker can split along them. Runs headless: the tenant comes from the content.
 */
final class ContentText
{
    /** @var array<int, string> */
    public const TEXT_FIELD_TYPES = [
        'text',
        'textarea',
        'richText',
        'translatableText',
        'translatableTextarea',
        'translatableRichText',
    ];

    /** @var array<int, string> */
    private const RICH_TEXT_TYPES = ['richText', 'translatableRichText'];

    /**
     * @param  bool  $withName  false leaves the name out — for a caller that
     *                          carries the title on its own (the UKI index)
     */
    public static function markdown(Content $content, bool $withName = true): string
    {
        $language = CmsLanguageCodes::defaultFor((int) $content->tenant_id);
        $parts = [];

        $name = $withName ? $content->localizedName($language) : '';
        if ($name !== '') {
            $parts[] = '# ' . $name;
        }

        $elements = $content->relationLoaded('elements') ? $content->elements : $content->elements()->get();

        foreach ($elements as $element) {
            $text = self::elementText($element, $language);

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return mb_trim(implode("\n\n", $parts));
    }

    public static function elementText(ContentElement $element, string $language): string
    {
        $definition = FieldHelper::getElementFields((string) $element->element_key);
        $data = is_array($element->data) ? $element->data : [];

        if (! $definition) {
            return '';
        }

        $parts = [];

        foreach (FieldHelper::flattenFields($definition['fields'] ?? []) as $field) {
            $type = (string) ($field['type'] ?? '');

            if (! in_array($type, self::TEXT_FIELD_TYPES, true)) {
                continue;
            }

            $key = preg_replace('/^\w+\./', '', (string) ($field['name'] ?? ''));
            $value = self::localize($data[$key] ?? null, $language);

            if ($value === '') {
                continue;
            }

            $parts[] = in_array($type, self::RICH_TEXT_TYPES, true) ? self::htmlToMarkdown($value) : mb_trim($value);
        }

        return mb_trim(implode("\n\n", array_filter($parts, fn(string $part): bool => $part !== '')));
    }

    private static function localize(mixed $value, string $language): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            return is_scalar($value) ? (string) $value : '';
        }

        if (is_string($value[$language] ?? null) && $value[$language] !== '') {
            return $value[$language];
        }

        foreach ($value as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private static function htmlToMarkdown(string $html): string
    {
        $text = preg_replace_callback(
            '#<h([1-6])[^>]*>(.*?)</h\1>#is',
            fn(array $match): string => "\n\n" . str_repeat('#', min((int) $match[1] + 1, 4)) . ' ' . mb_trim(strip_tags($match[2])) . "\n\n",
            $html,
        ) ?? $html;

        $text = preg_replace('#<li[^>]*>#i', "\n- ", $text) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|ul|ol|blockquote)>#i', "\n\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return mb_trim($text);
    }
}
