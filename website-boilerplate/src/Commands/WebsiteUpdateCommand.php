<?php

declare(strict_types=1);

namespace Noerd\Website\Commands;

use Illuminate\Console\Command;
use Noerd\Commands\Concerns\RequiresNoerdInstallation;

class WebsiteUpdateCommand extends Command
{
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:update-website {--force : Overwrite existing files without asking}';

    protected $description = 'Update the website module configuration';

    /**
     * The website module ships neither an app-configs/ folder nor a publishable
     * config file (config/website.php is only merged at runtime, never
     * published), so there is nothing to refresh. The command exists so
     * noerd:update-all covers the module instead of silently dropping it from
     * the project-wide update. The --force option is accepted because
     * noerd:update-all forwards it to every module update command.
     */
    public function handle(): int
    {
        if (! $this->ensureNoerdInstalled()) {
            return self::FAILURE;
        }

        $this->info('Website module: nothing to publish — configuration is merged at runtime.');

        return self::SUCCESS;
    }
}
