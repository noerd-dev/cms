<?php

declare(strict_types=1);

namespace Noerd\Website\Services;

use Illuminate\Support\Collection;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Cms\Support\SafeUrl;
use Noerd\Website\Models\GlobalParameter;
use Noerd\Website\Models\Navigation;
use Noerd\Website\Models\Page;

/**
 * Globals and navigation of the website. Bound as a SCOPED instance: the
 * memo below lives for one request (or job) only.
 */
class WebsiteService
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function getGlobals(int $tenantId): array
    {
        $selectedLanguage = $this->language();

        return $this->memo['globals:' . $tenantId . ':' . $selectedLanguage] ??= GlobalParameter::where('tenant_id', $tenantId)->get()
            ->mapWithKeys(function ($item) use ($selectedLanguage) {
                $decoded = json_decode((string) $item->value, true);

                if (is_array($decoded)) {
                    // A multilingual parameter: the selected language, else the first one.
                    if (isset($decoded[$selectedLanguage])) {
                        return [$item->key => $decoded[$selectedLanguage]];
                    }

                    return [$item->key => $decoded !== [] ? reset($decoded) : ''];
                }

                return [$item->key => $decoded ?? $item->value ?? ''];
            })->toArray();
    }

    /**
     * Every navigation group of the tenant, keyed by the lower-case
     * navigation key (`main`, `footer`, …) — one query for the items, one for
     * the pages they link.
     *
     * @return array<string, list<array{name: string, href: string, new_tab: bool, children: list<array<string, mixed>>}>>
     */
    public function getNavigations(int $tenantId, string $language, ?string $uuid = null): array
    {
        return $this->memo['navigations:' . $tenantId . ':' . $language . ':' . $uuid] ??= $this->items($tenantId)
            ->groupBy(fn(Navigation $item): string => mb_strtolower((string) $item->navigation_key))
            ->map(fn(Collection $items): array => $this->tree($items, $tenantId, $language, $uuid))
            ->all();
    }

    /**
     * One navigation group. A group the tenant has not created yet answers
     * with every item (the behaviour single-menu sites rely on).
     *
     * @return list<array{name: string, href: string, new_tab: bool, children: list<array<string, mixed>>}>
     */
    public function getNavigation(int $tenantId, string $group = 'main', ?string $language = null, ?string $uuid = null): array
    {
        $language ??= $this->language();
        $items = $this->items($tenantId);
        $inGroup = $items->filter(fn(Navigation $item): bool => mb_strtolower((string) $item->navigation_key) === mb_strtolower($group));

        return $this->tree($inGroup->isNotEmpty() ? $inGroup : $items, $tenantId, $language, $uuid);
    }

    /**
     * @return Collection<int, Navigation>
     */
    private function items(int $tenantId): Collection
    {
        return $this->memo['items:' . $tenantId] ??= Navigation::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'navigation_key', 'parent_id', 'sort_order', 'name', 'page_id', 'link', 'new_tab']);
    }

    /**
     * The items as a tree (top level + their children, by sort_order). Items
     * linking an inactive page, a page of another tenant or no target at all
     * are left out, a link that is no safe URL too.
     *
     * @param  Collection<int, Navigation>  $items
     * @return list<array<string, mixed>>
     */
    private function tree(Collection $items, int $tenantId, string $language, ?string $uuid): array
    {
        $pages = $this->pages($tenantId, $items->pluck('page_id')->filter()->unique()->values()->all());
        $ids = $items->pluck('id')->all();
        $byParent = $items->groupBy(fn(Navigation $item): int => in_array($item->parent_id, $ids, false) ? (int) $item->parent_id : 0);

        $build = function (int $parentId, int $depth) use (&$build, $byParent, $pages, $language, $uuid): array {
            $nodes = [];

            foreach ($byParent->get($parentId, collect()) as $item) {
                $node = $this->node($item, $pages, $language, $uuid);

                if ($node === null) {
                    continue;
                }

                $node['children'] = $depth < 3 ? $build((int) $item->id, $depth + 1) : [];
                $nodes[] = $node;
            }

            return $nodes;
        };

        return $build(0, 0);
    }

    /**
     * @param  array<int, Page>  $pages
     * @return array{name: string, href: string, new_tab: bool}|null
     */
    private function node(Navigation $item, array $pages, string $language, ?string $uuid): ?array
    {
        $href = null;

        if (! empty($item->link)) {
            $href = SafeUrl::orNull($item->link);
        } elseif (! empty($item->page_id)) {
            $page = $pages[(int) $item->page_id] ?? null;

            if ($page === null) {
                return null;
            }

            $slugs = $this->decode($page->slug);

            if (is_array($slugs) && $slugs !== []) {
                $href = SafeUrl::orNull($slugs[$language] ?? ($slugs[CmsLanguageCodes::active()[0]] ?? reset($slugs)));
            } elseif (is_string($slugs) && $slugs !== '') {
                $href = SafeUrl::orNull($slugs);
            } else {
                $href = route('website.index', ['uuid' => $uuid, 'page' => $item->page_id]);
            }
        } else {
            $href = '#';
        }

        if ($href === null) {
            return null;
        }

        $label = $this->decode($item->name);

        return [
            'name' => is_array($label) ? (string) ($label[$language] ?? (reset($label) ?: '')) : (string) ($label ?? ''),
            'href' => $href,
            'new_tab' => (bool) $item->new_tab,
        ];
    }

    /**
     * The ACTIVE pages of the tenant among the ids, keyed by id — one query.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, Page>
     */
    private function pages(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Page::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get(['id', 'slug'])
            ->keyBy('id')
            ->all();
    }

    private function decode(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }

    private function language(): string
    {
        return (string) \Noerd\Website\Support\WebsiteLanguage::current();
    }
}
