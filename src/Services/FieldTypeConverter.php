<?php

declare(strict_types=1);

namespace Noerd\Cms\Services;

use Noerd\Cms\Helpers\CollectionHelper;
use Noerd\Cms\Support\CmsLanguageCodes;

class FieldTypeConverter
{
    /**
     * Convert field data based on collection field type changes
     *
     * @param  array  $currentData  Current page data
     * @param  string  $collectionKey  Collection key to get field definitions
     * @return array Converted data
     */
    public static function convertCollectionData(array $currentData, string $collectionKey): array
    {
        $collectionFields = CollectionHelper::getCollectionFields($collectionKey);

        if (! $collectionFields || ! isset($collectionFields['fields'])) {
            // If collection config is not found, return original data unchanged
            return $currentData;
        }

        $convertedData = $currentData;

        foreach ($collectionFields['fields'] as $field) {
            $fieldName = str_replace('detailData.', '', $field['name']);
            $fieldType = $field['type'];

            // Skip if field not present in data
            if (! array_key_exists($fieldName, $currentData)) {
                continue;
            }

            $currentValue = $currentData[$fieldName];

            // Convert based on target field type
            if (in_array($fieldType, ['translatableText', 'translatableRichText', 'translatableTextarea'])) {
                $convertedData[$fieldName] = self::convertToTranslatableField($currentValue);
            } else {
                $convertedData[$fieldName] = self::convertFromTranslatableField($currentValue);
            }
        }

        return $convertedData;
    }

    /**
     * Convert data to translatable field format: a map of the tenant's active
     * language codes. The language set is tenant-configurable, so it is never
     * hardcoded here.
     *
     * @return array<string, mixed>
     */
    private static function convertToTranslatableField(mixed $value): array
    {
        // If already in translatable format, return as-is
        if (is_array($value) && CmsLanguageCodes::isLanguageMap($value)) {
            return $value;
        }

        // Copy the scalar value to every active language as a starting point;
        // structured arrays cannot be stringified and start with empty slots.
        $stringValue = is_scalar($value) ? (string) $value : '';

        return array_fill_keys(CmsLanguageCodes::active(), $stringValue);
    }

    /**
     * Convert data from translatable field format to simple field
     */
    private static function convertFromTranslatableField(mixed $value): mixed
    {
        if (is_array($value)) {
            // Only collapse arrays that actually look like translatable values.
            // Lists/repeater values must be preserved as-is, otherwise saving a
            // collection page would wipe the structured data.
            if (CmsLanguageCodes::isLanguageMap($value)) {
                // Prefer the default language, then any non-empty value.
                foreach (CmsLanguageCodes::active() as $code) {
                    if (isset($value[$code]) && $value[$code] !== '') {
                        return $value[$code];
                    }
                }

                foreach ($value as $languageValue) {
                    if ($languageValue !== null && $languageValue !== '') {
                        return $languageValue;
                    }
                }

                return '';
            }

            return $value;
        }

        return $value;
    }
}
