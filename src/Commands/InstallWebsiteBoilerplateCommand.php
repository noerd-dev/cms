<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Noerd\Commands\Concerns\PreparesModuleWorkspace;
use Noerd\Commands\Concerns\WritesHostAppConfigs;
use Noerd\Support\ModuleInstallContext;

/**
 * Copies the website boilerplate the CMS ships into app-modules/website, where
 * the project owns and customises it. Not a module installer: there is no
 * tenant app and nothing to update afterwards.
 */
class InstallWebsiteBoilerplateCommand extends Command
{
    use PreparesModuleWorkspace;
    use WritesHostAppConfigs;

    protected $signature = 'noerd:install-website {--force : Overwrite existing files without asking}';

    protected $description = 'Install website boilerplate from CMS module to app-modules/website';

    public function handle(): int
    {
        $this->info('Installing website boilerplate...');

        $sourceDir = dirname(__DIR__, 2) . '/website-boilerplate';
        $targetDir = base_path('app-modules/website');

        if (! is_dir($sourceDir)) {
            $this->error("Website boilerplate not found: {$sourceDir}");

            return self::FAILURE;
        }

        // Never delete a target that is its own git repository — it may hold
        // unpushed customizations. This guard also applies under --force.
        if (is_dir($targetDir . '/.git')) {
            $this->error("Directory {$targetDir} is a git repository. Remove it manually before reinstalling the boilerplate.");

            return self::FAILURE;
        }

        if (is_dir($targetDir) && ! $this->option('force')
            && ! $this->confirm("Directory {$targetDir} already exists. Do you want to overwrite it?")) {
            $this->info('Installation cancelled.');

            return self::INVALID;
        }

        try {
            if (is_dir($targetDir)) {
                $this->line('<comment>Removing existing directory...</comment>');
                File::deleteDirectory($targetDir);
            }

            if (! File::copyDirectory($sourceDir, $targetDir)) {
                throw new Exception("Failed to copy the boilerplate to {$targetDir}");
            }

            $this->line('<info>Copied the boilerplate to:</info> app-modules/website');

            // The copied module is required through the app-modules/* path repository;
            // the composer run itself is left to the operator — shelling out to
            // composer from inside artisan is fragile (PHP binary, memory limit, TTY).
            $this->prepareModuleWorkspace();
            $this->ensureQuickMenuButton(['apps' => ['CMS'], 'component' => 'quick-menu.website-link']);

            $this->info('Website boilerplate successfully installed!');
            $this->line('');

            // Run for the CMS installation, the migrations are offered once — by it.
            if (! ModuleInstallContext::isDependencyInstall()
                && $this->input->isInteractive()
                && $this->confirm('Would you like to run migrations now?', true)) {
                $this->call('migrate');
            }

            $this->line('<info>Next steps:</info>');
            $this->line('- Finish the registration with: composer require noerd/website');
            $this->line('- Review and customize the generated files in app-modules/website');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error installing website boilerplate: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
