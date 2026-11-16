<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\Page;

/**
 * Before noerd/cms 0.6 deleting a collection removed its entries through the
 * cms_pages foreign key, past the model hooks — their contents (and elements)
 * stayed behind with no record extending them.
 *
 * Only contents of the CMS's OWN content type (`cms.page`) that no cms_pages
 * row references are orphans: other modules register content types of their
 * own and are never touched. A dry run by default — `--force` deletes, through
 * the models, so the elements and their element collections go too.
 */
class PruneOrphanedContentsCommand extends Command
{
    protected $signature = 'cms:prune-orphaned-contents
                            {--tenant= : Only this tenant}
                            {--force : Delete the orphaned contents (otherwise only report them)}';

    protected $description = 'Report (or with --force delete) website-page contents no page references any more';

    public function handle(): int
    {
        $query = Content::query()
            ->acrossTenants()
            ->where('type', Page::contentType())
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('cms_pages')
                    ->whereColumn('cms_pages.content_id', 'contents.id');
            });

        if ($this->option('tenant')) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No orphaned contents found.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn("{$count} orphaned content(s) of type " . Page::contentType() . ' found. Run with --force to delete them.');

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->orderBy('id')->chunkById(100, function ($contents) use (&$deleted): void {
            foreach ($contents as $content) {
                DB::transaction(function () use ($content): void {
                    $content->elements()->get()->each->delete();
                    $content->delete();
                });

                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} orphaned content(s).");

        return self::SUCCESS;
    }
}
