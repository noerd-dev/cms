<?php

namespace Noerd\Website\Services;

use Noerd\Website\Models\GlobalParameter;
use Noerd\Website\Models\Navigation;
use Noerd\Website\Models\Page;

class WebsiteService
{
    public function getGlobals(int $tenantId): array
    {
        return GlobalParameter::where('tenant_id', $tenantId)->get()
            ->mapWithKeys(function ($item) {
                $decoded = json_decode($item->value, true);
                return [$item->key => ($decoded ?? $item->value)];
            })->toArray();
    }

    public function getNavigation(int $tenantId, string $group = 'main', string $language = 'de', ?string $hash = null): array
    {
        $query = Navigation::query()->where('tenant_id', $tenantId);
        $hasGroup = (clone $query)->where('navigation_key', $group)->exists();
        if ($hasGroup) {
            $query->where('navigation_key', $group);
        }

        $items = $query->orderBy('id')->get(['name', 'page_id', 'link', 'new_tab']);

        return $items->map(function ($item) use ($language, $hash) {
            $label = $item->name;
            if (is_string($label)) {
                $decoded = json_decode($label, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $label = $decoded[$language] ?? ($decoded['de'] ?? (is_array($decoded) ? reset($decoded) : ''));
                }
            }

            $href = '#';
            if (!empty($item->link)) {
                $href = $item->link;
            } elseif (!empty($item->page_id)) {
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
                    $href = route('website.index', ['hash' => $hash, 'page' => $item->page_id]);
                }

            }

            return [
                'label' => is_array($label) ? ($label[$language] ?? reset($label)) : ($label ?? ''),
                'href' => $href,
                'new_tab' => (bool) $item->new_tab,
            ];
        })->toArray();
    }
}
