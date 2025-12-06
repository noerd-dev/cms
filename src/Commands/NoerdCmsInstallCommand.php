<?php

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Noerd\Noerd\Traits\HasModuleInstallation;
use Noerd\Noerd\Traits\RequiresNoerdInstallation;

class NoerdCmsInstallCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:install-cms {--force : Overwrite existing files without asking} {--without-website : Skip automatic website module installation}';

    protected $description = 'Install noerd CMS content and navigation';

    public function handle(): int
    {
        $result = $this->runModuleInstallation();

        if ($result === 0) {
            // Register the CMS module
            $this->registerModule();

            // Install website module if it doesn't exist
            $this->installWebsiteIfNeeded();
        }

        return $result;
    }

    protected function getModuleName(): string
    {
        return 'CMS';
    }

    protected function getModuleKey(): string
    {
        return 'cms';
    }

    protected function getDefaultAppTitle(): string
    {
        return 'CMS';
    }

    protected function getAppIcon(): string
    {
        return 'cms::icons.app';
    }

    protected function getAppRoute(): string
    {
        return 'cms.dashboard';
    }

    protected function getSourceDir(): string
    {
        return base_path('vendor/noerd/cms/content');
    }

    protected function getNavigationSourceFolder(): string
    {
        return 'cms';
    }

    protected function getSnippetTitle(): string
    {
        return 'CMS';
    }

    /**
     * Register the CMS module with Composer.
     */
    private function registerModule(): void
    {
        $this->line('');
        $this->info('Registering CMS module...');

        try {
            // Run composer require to register the module
            $this->line('<comment>Running composer require noerd/cms...</comment>');
            exec('composer require noerd/cms 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $this->warn('Composer require failed, trying composer dump-autoload...');
            }

            // Run composer dump-autoload
            $this->line('<comment>Running composer dump-autoload...</comment>');
            exec('composer dump-autoload 2>&1', $dumpOutput, $dumpReturnCode);

            // Clear Laravel caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            // Remove services cache to force re-discovery
            $servicesCache = base_path('bootstrap/cache/services.php');
            if (file_exists($servicesCache)) {
                unlink($servicesCache);
            }

            $this->line('<info>CMS module registered successfully.</info>');
        } catch (Exception $e) {
            $this->warn('Module registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Install website module if it doesn't already exist.
     */
    private function installWebsiteIfNeeded(): void
    {
        // Check if website installation should be skipped
        if ($this->option('without-website')) {
            $this->line('<comment>Skipping website module installation (--without-website flag provided).</comment>');

            return;
        }

        $websiteDir = base_path('app-modules/website');

        if (is_dir($websiteDir)) {
            $this->line('<comment>Website module already exists, skipping installation.</comment>');

            return;
        }

        $this->line('');
        $this->info('Website module not found, installing automatically...');

        try {
            // Execute the noerd:install-website command
            $exitCode = Artisan::call('noerd:install-website');

            if ($exitCode === 0) {
                $this->line('<info>Website module installed successfully.</info>');
            } else {
                $this->warn('Website module installation failed.');
            }
        } catch (Exception $e) {
            $this->warn('Failed to install website module: ' . $e->getMessage());
        }
    }
}
