<?php

namespace Noerd\Cms\Services;

use Illuminate\Database\Eloquent\Model;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Traits\HandlesPageElements;

class PageElementService
{
    use HandlesPageElements {
        localizeArray as protected handlesLocalizeArray;
    }

    /**
     * Decode a Page model's `data` JSON and resolve translatable arrays
     * for the given language. Used by collection-driven detail templates
     * (services, projects) that don't render via the element loop.
     *
     * @return array<string, mixed>
     */
    public function processCollectionPageData(Model $page, string $language = 'de'): array
    {
        $raw = $page->getRawOriginal('data');
        $data = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);

        $data = $this->injectElementCollections($page, $data);

        return $this->handlesLocalizeArray($data, $language);
    }

    /**
     * Merge the rows of any element collections owned by this entry back into its
     * data under their `owner_field` key, so frontend templates that used to read an
     * inline repeater (e.g. `triggers_items`) keep working unchanged.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function injectElementCollections(Model $page, array $data): array
    {
        if (! $page->getKey()) {
            return $data;
        }

        Collection::query()
            ->where('page_id', $page->getKey())
            ->where('is_element_collection', true)
            ->with('rows')
            ->get()
            ->each(function (Collection $elementCollection) use (&$data): void {
                if (! $elementCollection->owner_field) {
                    return;
                }

                $data[$elementCollection->owner_field] = $elementCollection->rows
                    ->map(fn ($row) => is_array($row->data) ? $row->data : [])
                    ->all();
            });

        return $data;
    }

    /**
     * Resolve all project Pages whose `services_slugs` contains the given
     * service Page's slug. Returns an array of normalized project tiles
     * ready for rendering on a service detail page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function relatedProjectsForService(Model $servicePage, string $language = 'de'): array
    {
        $serviceSlug = $this->slugSegment($servicePage, $language);

        if (! $serviceSlug) {
            return [];
        }

        $projectsCollection = Collection::where('tenant_id', $servicePage->tenant_id)
            ->where('collection_key', 'PROJECTS')
            ->first();

        if (! $projectsCollection) {
            return [];
        }

        $tiles = [];
        foreach ($projectsCollection->rows as $project) {
            $data = $this->processCollectionPageData($project, $language);
            $slugs = $data['services_slugs'] ?? [];

            $matchedSlugs = array_map(
                static fn ($entry) => is_array($entry) ? ($entry['slug'] ?? '') : (string) $entry,
                is_array($slugs) ? $slugs : [],
            );

            if (! in_array($serviceSlug, $matchedSlugs, true)) {
                continue;
            }

            $projectSlugs = $project->slug ?? [];
            $url = $projectSlugs[$language] ?? ($projectSlugs['de'] ?? '');

            $tiles[] = [
                'url' => $url ?: '#',
                'title' => $data['title'] ?? '',
                'company' => $data['company'] ?? '',
                'bg_hex' => $data['bg_hex'] ?? '#f5f5f5',
                'thumbnail' => $data['thumbnail'] ?? null,
            ];
        }

        return $tiles;
    }

    /**
     * Extract the service slug segment (last path component) of a page slug.
     */
    private function slugSegment(Model $page, string $language): ?string
    {
        $slugs = $page->slug ?? [];
        $fullSlug = $slugs[$language] ?? ($slugs['de'] ?? null);

        if (! $fullSlug) {
            return null;
        }

        $parts = array_filter(explode('/', $fullSlug));

        return $parts ? (string) end($parts) : null;
    }
}
