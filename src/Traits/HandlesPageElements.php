<?php

namespace Noerd\Cms\Traits;

use Noerd\Cms\Models\Collection;
use Noerd\Cms\Support\CmsLanguageCodes;

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

        // Preload element collections owned by these elements, grouped by owner,
        // so their rows can be merged back into each element's data (one query).
        $elementCollectionsByOwner = Collection::query()
            ->where('is_element_collection', true)
            ->whereIn('element_page_id', $pageElements->pluck('id'))
            ->with('rows')
            ->get()
            ->groupBy('element_page_id');

        foreach ($pageElements as $pageElement) {
            $elementKey = $pageElement->element_key ?? 'text_block_1_column';

            if (empty(mb_trim($elementKey))) {
                $elementKey = 'text_block_1_column';
            }

            $rawData = $this->decodeElementData($pageElement->data);

            foreach ($elementCollectionsByOwner->get($pageElement->id, collect()) as $elementCollection) {
                if (! $elementCollection->owner_field) {
                    continue;
                }
                $rawData[$elementCollection->owner_field] = $elementCollection->rows
                    ->map(fn($row) => is_array($row->data) ? $row->data : [])
                    ->all();
            }

            $elements[] = [
                'id' => $pageElement->id,
                'key' => $elementKey,
                'data' => (object) $this->localizeElementData($rawData, $selectedLanguage),
            ];
        }

        return $elements;
    }

    /**
     * Get component mapping for element keys to Volt component paths
     */
    public function getComponentMapping(): array
    {
        $mapping = [];

        $customElementsPath = config('noerd_cms.page_elements_path');
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
            glob(base_path('resources/views/components/elements/' . $fileBase . '.blade.php')) ?: [],
        );
        $ymlMatches = array_merge(
            glob(base_path('app-modules/*/resources/views/components/elements/' . $fileBase . '.yml')) ?: [],
            glob(base_path('resources/views/components/elements/' . $fileBase . '.yml')) ?: [],
        );

        return ! empty($bladeMatches) && ! empty($ymlMatches);
    }

    /**
     * Element data is normally an array (the model casts the JSON column), but
     * historic writes pushed an already encoded string through that cast, which
     * stored the JSON wrapped in JSON. Unwrap every layer so such a row renders
     * empty instead of taking the whole page down with a TypeError.
     *
     * @return array<string, mixed>
     */
    protected function decodeElementData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        for ($depth = 0; is_string($data) && $depth < 3; $depth++) {
            $data = json_decode($data, true);
        }

        return is_array($data) ? $data : [];
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

        // The tenant's default language is the fallback when a value has not been
        // translated yet — never a hard-coded 'de'.
        $fallbackLanguage = CmsLanguageCodes::active()[0] ?? 'de';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isTranslatableArray($value)) {
                    $resolved = $value[$language] ?? $value[$fallbackLanguage] ?? reset($value);
                    if (is_array($resolved)) {
                        $resolved = $resolved[$language] ?? $resolved[$fallbackLanguage] ?? reset($resolved);
                    }
                    $localized[$key] = is_string($resolved) ? $resolved : '';
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
        // Derived from the configured CMS languages, so a tenant-added language
        // (e.g. Danish) is recognised without touching the framework.
        return CmsLanguageCodes::isLanguageMap($array);
    }
}
