<?php

namespace Noerd\Cms\Traits;

trait HandlesPageElements
{
    /**
     * Process page elements for frontend rendering
     *
     * @param  mixed  $page
     */
    public function processPageElements($page, string $selectedLanguage = 'de'): array
    {
        $elements = [];

        if (! $page || ! method_exists($page, 'elements')) {
            return $elements;
        }

        $pageElements = $page->elements;

        foreach ($pageElements as $pageElement) {
            $elementKey = $pageElement->element_key ?? 'text_block_1_column';

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

        $customElementsPath = env('CMS_PAGE_ELEMENTS_PATH');
        $bladeFiles = [];

        if (! empty($customElementsPath) && is_dir(base_path($customElementsPath))) {
            $customFiles = glob(base_path($customElementsPath . '/*.blade.php')) ?: [];
            $bladeFiles = array_merge($bladeFiles, $customFiles);
        }

        $fallbackFiles = glob(base_path('app-modules/*/resources/views/components/elements/*.blade.php')) ?: [];
        $projectLevelFiles = glob(base_path('resources/views/components/elements/*.blade.php')) ?: [];
        $fallbackFiles = array_merge($fallbackFiles, $projectLevelFiles);
        $allFiles = array_merge($fallbackFiles, $bladeFiles);

        $uniqueFiles = [];
        foreach ($allFiles as $filePath) {
            $fileName = basename($filePath, '.blade.php');
            $uniqueFiles[$fileName] = $filePath;
        }

        foreach ($uniqueFiles as $fileName => $filePath) {
            $elementKey = str_replace('-', '_', $fileName);
            $mapping[$elementKey] = 'elements.' . $fileName;
        }

        return $mapping;
    }

    /**
     * Check whether both the Blade component and the YAML definition exist
     */
    public function elementDefinitionExists(string $componentName): bool
    {
        $parts = explode('.', $componentName);
        $fileBase = end($parts) ?: $componentName;

        $bladeMatches = array_merge(
            glob(base_path('app-modules/*/resources/views/components/elements/' . $fileBase . '.blade.php')) ?: [],
            glob(base_path('resources/views/components/elements/' . $fileBase . '.blade.php')) ?: []
        );
        $ymlMatches = array_merge(
            glob(base_path('app-modules/*/resources/views/components/elements/' . $fileBase . '.yml')) ?: [],
            glob(base_path('resources/views/components/elements/' . $fileBase . '.yml')) ?: []
        );

        return ! empty($bladeMatches) && ! empty($ymlMatches);
    }

    /**
     * Localize element data by extracting the correct language value
     */
    protected function localizeElementData(array $data, string $selectedLanguage = 'de'): array
    {
        return $this->localizeArray($data, $selectedLanguage);
    }

    /**
     * Recursively localize array data
     */
    protected function localizeArray(array $data, string $language): array
    {
        $localized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isTranslatableArray($value)) {
                    $localized[$key] = $value[$language] ?? $value['de'] ?? (is_array($value) ? reset($value) : '');
                } else {
                    $localized[$key] = $this->localizeArray($value, $language);
                }
            } else {
                $localized[$key] = $value;
            }
        }

        return $localized;
    }

    /**
     * Check if an array contains language keys (is translatable)
     */
    protected function isTranslatableArray(array $array): bool
    {
        $languageCodes = ['de', 'en', 'fr', 'es', 'it', 'nl'];
        $keys = array_keys($array);

        return ! empty($keys) && count(array_intersect($keys, $languageCodes)) === count($keys);
    }
}
