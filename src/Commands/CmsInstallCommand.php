<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Noerd\Cms\Services\DefaultHomepageSeeder;
use Noerd\Support\ModuleInstallContext;
use Noerd\Traits\HasModuleInstallation;

class CmsInstallCommand extends Command
{
    use HasModuleInstallation;

    protected $signature = 'noerd:install-cms
                            {--force : Overwrite existing files without asking}
                            {--migrate : Run migrations without asking (required to migrate in non-interactive runs)}
                            {--build : Run npm build without asking (required to build in non-interactive runs)}';

    protected $description = 'Install noerd CMS content and navigation';

    public function handle(): int
    {
        return $this->runModuleInstallation();
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
     * media app too. A missing media module is installed first, as a dependency.
     *
     * @return array<string, string>
     */
    protected function getRequiredModules(): array
    {
        return ['MEDIA' => 'noerd:install-media'];
    }

    /**
     * @return array<string>
     */
    protected function getAdditionalSubdirectories(): array
    {
        return ['forms'];
    }

    /**
     * @return array<int, string>
     */
    protected function getConfigFiles(): array
    {
        return ['noerd_cms.php'];
    }

    /**
     * The website boilerplate is offered once, by the installation the user
     * started — and BEFORE the frontend build, which has to see its views.
     */
    protected function publishModuleExtras(bool $update): void
    {
        if (! $update && ! ModuleInstallContext::isDependencyInstall()) {
            $this->installWebsiteIfNeeded();
        }
    }

    /**
     * Idempotent: tenants created since the install get their starter homepage,
     * and the quick-menu keeps the "To Website" button — the shared writer
     * replaces a same-component entry wholesale, so an installation still
     * carrying the removed `policy: canCms` gate migrates to the `apps:` key.
     */
    protected function ensureModuleSetup(): void
    {
        $this->seedDefaultHomepage();
        $this->ensureQuickMenuButton(['apps' => ['CMS'], 'component' => 'quick-menu.website-link']);
    }

    /**
     * Seed a default homepage (page + cms_settings) for every tenant that does
     * not have one yet. Runs once tenants have been assigned and the migrations
     * were offered, so a fresh installation starts with a usable starter page.
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
     * Install the website boilerplate if the user confirms. It runs as a
     * dependency: migrations are offered once, by this command.
     */
    private function installWebsiteIfNeeded(): void
    {
        if (is_dir(base_path('app-modules/website'))) {
            $this->line('<comment>Website module already exists.</comment>');

            return;
        }

        $this->line('');
        if (! $this->input->isInteractive() || ! $this->confirm('Would you like to install the website boilerplate?', false)) {
            $this->line('<comment>Skipping the website boilerplate. Install it later with: php artisan noerd:install-website</comment>');

            return;
        }

        try {
            if ($this->installDependencyModule('noerd:install-website') !== self::SUCCESS) {
                $this->warn('Website module installation failed.');
            }
        } catch (Exception $e) {
            $this->warn('Failed to install website module: ' . $e->getMessage());
        }
    }
}
