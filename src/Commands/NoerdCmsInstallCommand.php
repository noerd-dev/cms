<?php

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\RequiresNoerdInstallation;

class NoerdCmsInstallCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:install-cms {--force : Overwrite existing files without asking}';

    protected $description = 'Install noerd CMS content and navigation';

    public function handle(): int
    {
        $this->installMediaIfNeeded();

        $result = $this->runModuleInstallation();

        if ($result === 0) {
            // Publish config file
            $this->publishConfig();

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
        return dirname(__DIR__, 2) . '/app-contents/cms';
    }

    /**
     * @return array<string>
     */
    protected function getAdditionalSubdirectories(): array
    {
        return ['collections', 'forms'];
    }

    /**
     * Publish the CMS config file to the project's config directory.
     */
    private function publishConfig(): void
    {
        $source = __DIR__ . '/../../config/noerd_cms.php';
        $destination = config_path('noerd_cms.php');

        if (file_exists($destination) && ! $this->option('force')) {
            if (! $this->confirm('Config file config/noerd_cms.php already exists. Overwrite?', false)) {
                $this->line('<comment>Skipped publishing config file.</comment>');

                return;
            }
        }

        copy($source, $destination);
        $this->line('<info>Published config file:</info> config/noerd_cms.php');
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
     * Install website module if user confirms.
     */
    private function installWebsiteIfNeeded(): void
    {
        $websiteDir = base_path('app-modules/website');

        if (is_dir($websiteDir)) {
            $this->line('<comment>Website module already exists.</comment>');

            return;
        }

        $this->line('');
        if (! $this->confirm('Would you like to install the website boilerplate?', false)) {
            $this->line('<comment>Skipping website module installation.</comment>');

            return;
        }

        try {
            $exitCode = Artisan::call('noerd:install-website', [], $this->output);

            if ($exitCode === 0) {
                $this->line('<info>Website module installed successfully.</info>');
            } else {
                $this->warn('Website module installation failed.');
            }
        } catch (Exception $e) {
            $this->warn('Failed to install website module: ' . $e->getMessage());
        }
    }

    /**
     * Install media module if filesystem is not configured.
     */
    private function installMediaIfNeeded(): void
    {
        $filesystemsPath = base_path('config/filesystems.php');

        if (file_exists($filesystemsPath)) {
            $content = file_get_contents($filesystemsPath);
            if (str_contains($content, "'media' =>")) {
                $this->line('<comment>Media filesystem already configured.</comment>');

                return;
            }
        }

        $this->line('');
        $this->info('Media filesystem not configured, running noerd:install-media...');

        try {
            $exitCode = Artisan::call('noerd:install-media', [], $this->output);

            if ($exitCode === 0) {
                $this->line('<info>Media module configured successfully.</info>');
            }
        } catch (Exception $e) {
            $this->warn('Failed to configure media: ' . $e->getMessage());
        }
    }
}
