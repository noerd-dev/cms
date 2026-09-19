<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Noerd\Cms\Services\DefaultHomepageSeeder;
use Noerd\Models\TenantApp;
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
        // The CMS does not work without the media library (image fields store a
        // media id). Media is installed BEFORE the CMS, so its app exists by the
        // time the CMS asks which tenants it should be assigned to — that one
        // prompt assigns MEDIA along with it (getRequiredAppKeys()).
        $this->installMediaIfNeeded();

        $result = $this->runModuleInstallation();

        if ($result === 0) {
            // Publish config file
            $this->publishConfig();

            // Seed a starter homepage for tenants that don't have one yet
            $this->seedDefaultHomepage();

            // Install website module if it doesn't exist
            $this->installWebsiteIfNeeded();

            // Ensure the quick-menu carries the "To Website" button
            $this->installQuickMenuConfig();
        }

        return $result;
    }

    /**
     * Ensure the quick-menu config contains the "To Website" button. The shared
     * writer replaces a same-component entry wholesale, so an installation still
     * carrying the removed `policy: canCms` gate — which fails closed and hides
     * the button — migrates to the `apps:` key on every install and update.
     */
    protected function installQuickMenuConfig(): void
    {
        $this->ensureQuickMenuButton(['apps' => ['CMS'], 'component' => 'quick-menu.website-link']);
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
        return 'heroicon:outline:rectangle-group';
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
     * Image fields of pages, elements and collections store a MEDIA id and are
     * picked through the media library — a tenant running the CMS must run the
     * media app too.
     *
     * @return array<string>
     */
    protected function getRequiredAppKeys(): array
    {
        return ['MEDIA'];
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
     * Publish the config file only when the project does not have one yet —
     * the prompt-free variant for the update command, so a new config key
     * reaches an existing project without overwriting local changes.
     */
    protected function publishConfigIfMissing(): void
    {
        $destination = config_path('noerd_cms.php');

        if (file_exists($destination)) {
            return;
        }

        copy(__DIR__ . '/../../config/noerd_cms.php', $destination);
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
     * Install the media module unless it is fully set up already — both the
     * `media` disk and the MEDIA tenant app have to exist, because the CMS
     * assigns that app to its own tenants (getRequiredAppKeys()).
     *
     * It runs as a DEPENDENCY: the nested command publishes and registers, but
     * does not ask its own question about tenants.
     */
    private function installMediaIfNeeded(): void
    {
        $filesystemsPath = base_path('config/filesystems.php');
        $diskConfigured = file_exists($filesystemsPath)
            && str_contains((string) file_get_contents($filesystemsPath), "'media' =>");

        // The table is missing on a project where noerd itself is not installed
        // yet — the media command installs the base package before anything else.
        $appRegistered = Schema::hasTable('tenant_apps')
            && TenantApp::where('name', 'MEDIA')->exists();

        if ($diskConfigured && $appRegistered) {
            $this->line('<comment>Media module already installed.</comment>');

            return;
        }

        $this->line('');
        $this->info('The CMS requires the media library, running noerd:install-media...');

        try {
            $exitCode = $this->installDependencyModule('noerd:install-media');

            if ($exitCode === 0) {
                $this->line('<info>Media module configured successfully.</info>');
            }
        } catch (Exception $e) {
            $this->warn('Failed to configure media: ' . $e->getMessage());
        }
    }
}
