<?php

namespace Noerd\Website\Services;

use Noerd\Cms\Models\Page as CmsPage;
use Noerd\Website\Models\Page;

class PageElementService
{
    /**
     * Process page elements for frontend rendering
     *
     * @param  Page|CmsPage  $page
     */
    public function processPageElements($page, string $selectedLanguage = 'de'): array
    {
        $elements = [];

        // Handle case where page might not have elements relationship loaded
        if (! $page || ! method_exists($page, 'elements')) {
            return $elements;
        }

        $pageElements = $page->elements;

        foreach ($pageElements as $pageElement) {
            // Get element_key with robust fallback
            $elementKey = $pageElement->element_key ?? 'text_block_1_column';

            // Additional safety check - ensure it's not empty string
            if (empty(mb_trim($elementKey))) {
                $elementKey = 'text_block_1_column';
            }

            $element['id'] = $pageElement->id;
            $element['key'] = $elementKey;
            $element['data'] = (object) $this->localizeElementData(
                json_decode($pageElement->data, true) ?? [],
                $selectedLanguage,
            );
            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * Get component mapping for element keys to Volt component paths
     */
    public function getComponentMapping(): array
    {
        $mapping = [];

        // Get custom elements path from environment variable
        $customElementsPath = env('CMS_PAGE_ELEMENTS_PATH');

        $bladeFiles = [];

        // If custom path is provided, use it first
        if (! empty($customElementsPath) && is_dir(base_path($customElementsPath))) {
            $customFiles = glob(base_path($customElementsPath . '/*.blade.php')) ?: [];
            $bladeFiles = array_merge($bladeFiles, $customFiles);
        }

        // Fallback: Discover all Livewire/Volt element components across app-modules
        $fallbackFiles = glob(base_path('app-modules/*/resources/views/livewire/elements/*.blade.php')) ?: [];

        // Merge custom and fallback files, custom files take precedence
        $allFiles = array_merge($fallbackFiles, $bladeFiles);

        // Remove duplicates based on filename (custom files will override fallback)
        $uniqueFiles = [];
        foreach ($allFiles as $filePath) {
            $fileName = basename($filePath, '.blade.php');
            $uniqueFiles[$fileName] = $filePath;
        }

        foreach ($uniqueFiles as $fileName => $filePath) {
            $elementKey = str_replace('-', '_', $fileName); // snake_case key stored in DB
            // Map to Volt component name
            $mapping[$elementKey] = 'elements.' . $fileName;
        }

        return $mapping;
    }

    /**
     * Check whether both the Blade component and the YAML definition exist for a given element component name.
     *
     * @example componentName: "elements.text-block-1-column"
     */
    public function elementDefinitionExists(string $componentName): bool
    {
        // Extract the file base name from dot notation (e.g., elements.text-block-1-column -> text-block-1-column)
        $parts = explode('.', $componentName);
        $fileBase = end($parts) ?: $componentName;

        // Look for Blade component in any app-module under livewire/elements
        $bladeMatches = glob(base_path('app-modules/*/resources/views/livewire/elements/' . $fileBase . '.blade.php')) ?: [];

        // Look for YAML definition alongside elements (search all modules for flexibility)
        $ymlMatches = glob(base_path('app-modules/*/resources/views/livewire/elements/' . $fileBase . '.yml')) ?: [];

        return ! empty($bladeMatches) && ! empty($ymlMatches);
    }

    /**
     * Localize element data by extracting the correct language value
     * from multilingual arrays based on the selected language
     */
    private function localizeElementData(array $data, string $selectedLanguage = 'de'): array
    {
        return $this->localizeArray($data, $selectedLanguage);
    }

    /**
     * Recursively localize array data
     */
    private function localizeArray(array $data, string $language): array
    {
        $localized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Check if this is a translatable field (has language keys)
                if ($this->isTranslatableArray($value)) {
                    // Extract the value for the selected language with fallback
                    $localized[$key] = $value[$language] ?? $value['de'] ?? (is_array($value) ? reset($value) : '');
                } else {
                    // Recursively process nested arrays
                    $localized[$key] = $this->localizeArray($value, $language);
                }
            } else {
                // Keep non-array values as is
                $localized[$key] = $value;
            }
        }

        return $localized;
    }

    /**
     * Check if an array contains language keys (is translatable)
     */
    private function isTranslatableArray(array $array): bool
    {
        // Check if the array has language codes as keys
        $languageCodes = ['de', 'en', 'fr', 'es', 'it', 'nl']; // Add more as needed
        $keys = array_keys($array);

        // If all keys are language codes, it's a translatable array
        return ! empty($keys) && count(array_intersect($keys, $languageCodes)) === count($keys);
    }
}
