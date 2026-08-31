<?php

namespace Noerd\Website\Services;

use Noerd\Website\Models\GlobalParameter;
use Noerd\Website\Models\Language;
use Noerd\Website\Models\Navigation;
use Noerd\Website\Models\Page;

class WebsiteService
{
    public function getGlobals(int $tenantId): array
    {
        // Get the current language (from session or default)
        $selectedLanguage = session('selectedLanguage');
        if (! $selectedLanguage) {
            $defaultLanguage = Language::where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
            $selectedLanguage = $defaultLanguage ? $defaultLanguage->code : 'en';
        }

        return GlobalParameter::where('tenant_id', $tenantId)->get()
            ->mapWithKeys(function ($item) use ($selectedLanguage) {
                $decoded = json_decode($item->value, true);

                // Handle different data types properly
                if (is_array($decoded)) {
                    // Check if this is a multilingual parameter (array with language keys)
                    if (isset($decoded[$selectedLanguage])) {
                        // Return the value for the selected language
                        return [$item->key => $decoded[$selectedLanguage]];
                    }

                    // If selected language doesn't exist, try to find any language value
                    if (! empty($decoded)) {
                        // Return the first available language value
                        return [$item->key => reset($decoded)];
                    }

                    // If array is empty, return empty string
                    return [$item->key => ''];
                }

                // If not an array, return the decoded value (could be string, number, etc.)
                return [$item->key => $decoded ?? $item->value ?? ''];
            })->toArray();
    }

    public function getNavigation(int $tenantId, string $group = 'main', string $language = 'de', ?string $uuid = null): array
    {
        $query = Navigation::query()->where('tenant_id', $tenantId);
        $hasGroup = (clone $query)->where('navigation_key', $group)->exists();
        if ($hasGroup) {
            $query->where('navigation_key', $group);
        }

        $items = $query->orderBy('id')->get(['name', 'page_id', 'link', 'new_tab']);

        return $items->map(function ($item) use ($language, $uuid) {
            $label = $item->name;
            if (is_string($label)) {
                $decoded = json_decode($label, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $label = $decoded[$language] ?? ($decoded['de'] ?? (is_array($decoded) ? reset($decoded) : ''));
                }
            }

            $href = '#';
            if (! empty($item->link)) {
                $href = $item->link;
            } elseif (! empty($item->page_id)) {
                $page = Page::find($item->page_id);
                if ($page && $page->slug) {
                    // Extract slug for current language - handle both array and JSON string
                    $slugs = $page->slug;

                    // If it's a JSON string, decode it
                    if (is_string($slugs)) {
                        $decoded = json_decode($slugs, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $slugs = $decoded;
                        }
                    }

                    if (is_array($slugs)) {
                        $href = $slugs[$language] ?? ($slugs['de'] ?? reset($slugs));
                    } else {
                        $href = (string) $slugs; // Fallback if neither array nor valid JSON
                    }
                } else {
                    $href = route('website.index', ['uuid' => $uuid, 'page' => $item->page_id]);
                }

            }

            return [
                'name' => is_array($label) ? ($label[$language] ?? reset($label)) : ($label ?? ''),
                'href' => $href,
                'new_tab' => (bool) $item->new_tab,
            ];
        })->toArray();
    }
}
