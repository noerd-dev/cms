<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Illuminate\Console\Command;
use Noerd\Cms\Services\FormTypeSyncService;

class SyncFormTypesCommand extends Command
{
    protected $signature = 'cms:sync-form-types
                            {--tenant= : Sync for one tenant (id)}
                            {--force : Force sync even if files haven\'t changed}';

    protected $description = 'Sync form types from YML files to database';

    public function handle(FormTypeSyncService $syncService): int
    {
        $this->info('Syncing form types from YML files...');
        $this->newLine();

        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $force = (bool) $this->option('force');

        $results = $syncService->sync($tenantId, $force);

        // Display results
        if ($results['synced'] > 0) {
            $this->info("Synced: {$results['synced']} form type(s)");
        }

        if ($results['skipped'] > 0) {
            $this->comment("Skipped: {$results['skipped']} form type(s) (no changes)");
        }

        if ($results['deactivated'] > 0) {
            $this->comment("Deactivated: {$results['deactivated']} form type(s) whose YML file is gone");
        }

        if ($results['errors'] > 0) {
            $this->error("Errors: {$results['errors']}");
        }

        // Display detailed messages if verbose
        if (($this->option('verbose') || $results['errors'] > 0) && ! empty($results['messages'])) {
            $this->newLine();
            $this->line('Detailed messages:');
            foreach ($results['messages'] as $message) {
                $this->line('  - ' . $message);
            }
        }

        $this->newLine();

        if ($results['errors'] > 0) {
            $this->error('Sync completed with errors.');

            return self::FAILURE;
        }

        $this->info('Sync completed successfully!');

        return self::SUCCESS;
    }
}
