<?php

namespace Noerd\Cms\Services;

use Noerd\Cms\Helpers\CollectionHelper;

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
            $fieldName = str_replace('model.', '', $field['name']);
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
     * Convert data to translatable field format
     *
     * @param  mixed  $value
     */
    private static function convertToTranslatableField($value): array
    {
        // If already in translatable format, return as-is
        if (is_array($value) && (isset($value['de']) || isset($value['en']))) {
            return $value;
        }

        // Convert string to translatable format
        if (is_string($value)) {
            return [
                'de' => $value,
                'en' => $value, // Copy value to both languages as starting point
            ];
        }

        // Default fallback
        $stringValue = (string) $value;

        return [
            'de' => $stringValue,
            'en' => $stringValue, // Copy value to both languages as starting point
        ];
    }

    /**
     * Convert data from translatable field format to simple field
     *
     * @param  mixed  $value
     * @return mixed
     */
    private static function convertFromTranslatableField($value)
    {
        // If it's a translatable array, extract the German value as default
        if (is_array($value)) {
            return $value['de'] ?? $value['en'] ?? '';
        }

        // If it's not an array, return the original value with its original type
        return $value;
    }
}
