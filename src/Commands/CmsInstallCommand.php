<?php

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Noerd\Cms\Services\DefaultHomepageSeeder;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\RequiresNoerdInstallation;

class CmsInstallCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:install-cms {--force : Overwrite existing files without asking}';

    protected $description = 'Install noerd CMS content and navigation';

    public function handle(): int
    {
        $result = $this->runModuleInstallation();

        if ($result === 0) {
            // Publish config file
            $this->publishConfig();

            // Ensure the media filesystem the CMS media pickers rely on
            $this->installMediaIfNeeded();

            // Seed a starter homepage for tenants that don't have one yet
            $this->seedDefaultHomepage();

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
        return dirname(__DIR__, 2) . '/app-configs/cms';
    }

    /**
     * @return array<string>
     */
    protected function getAdditionalSubdirectories(): array
    {
        return ['forms'];
    }

    /**
     * Publish the CMS config file to the project's config directory.
     */
    protected function publishConfig(): void
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
     * Seed a default homepage (page + cms_settings) for every tenant that does
     * not have one yet. Runs at install time, once tenants have been assigned,
     * so a fresh installation starts with a usable starter page. Idempotent.
     */
    protected function seedDefaultHomepage(): void
    {
        try {
            (new DefaultHomepageSeeder())->seedMissingHomepages();
            $this->line('<info>Default homepage ensured for all tenants.</info>');
        } catch (Exception $e) {
            $this->warn('Could not seed default homepage: ' . $e->getMessage());
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
