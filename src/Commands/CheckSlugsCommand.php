<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Noerd\Cms\Services\PageSlugService;

/**
 * Read-only report of stored page slugs that break the slug rule of noerd/cms
 * 0.6 (PageSlugService): not in the normalized shape, naming another host,
 * under a reserved prefix, or used by two pages of a tenant (in any of the
 * languages). Nothing is changed — an editor fixes them in the page editor,
 * whose next save normalizes the slug.
 */
class CheckSlugsCommand extends Command
{
    protected $signature = 'cms:check-slugs {--tenant= : Only this tenant}';

    protected $description = 'Report stored page slugs that violate the slug rules (read only)';

    public function handle(PageSlugService $slugs): int
    {
        $query = DB::table('cms_pages')->whereNotNull('slug')->orderBy('id');

        if ($this->option('tenant')) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }

        $rows = [];
        $seen = [];

        foreach ($query->cursor() as $page) {
            $map = json_decode((string) $page->slug, true);
            $map = is_string($map) ? json_decode($map, true) : $map;

            if (! is_array($map)) {
                continue;
            }

            foreach ($map as $language => $slug) {
                if (! is_string($slug) || $slug === '') {
                    continue;
                }

                $problem = $slugs->violation($slug) ?? ($slugs->normalize($slug) !== $slug ? 'not normalized → ' . $slugs->normalize($slug) : null);

                $key = $page->tenant_id . '|' . $slugs->normalize($slug);
                $owner = $seen[$key] ?? null;

                if ($owner !== null && $owner !== $page->id) {
                    $problem = mb_trim(($problem ? $problem . '; ' : '') . "also used by page {$owner}");
                }

                $seen[$key] ??= $page->id;

                if ($problem !== null) {
                    $rows[] = [$page->tenant_id, $page->id, $language, $slug, $problem];
                }
            }
        }

        if ($rows === []) {
            $this->info('All page slugs follow the rules.');

            return self::SUCCESS;
        }

        $this->table(['Tenant', 'Page', 'Language', 'Slug', 'Problem'], $rows);
        $this->warn(count($rows) . ' slug(s) violate the rules. They keep working until they are edited; the next save normalizes them.');

        return self::SUCCESS;
    }
}
