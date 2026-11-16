<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Noerd\Cms\Commands\Concerns\EnsuresWebsiteLinkButton;
use Noerd\Cms\Forms\FormYamlMigrator;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Services\DefaultHomepageSeeder;
use Noerd\Cms\Services\FormTypeSyncService;
use Noerd\Cms\Support\LegacyCmsConfig;
use Noerd\Commands\Concerns\HasModuleInstallation;
use Noerd\Commands\Support\ModuleInstallContext;
use Noerd\Models\Tenant;
use Noerd\Support\TenantContext;

class CmsInstallCommand extends Command
{
    use EnsuresWebsiteLinkButton;
    use HasModuleInstallation {
        runModuleUpdate as runHasModuleUpdate;
    }

    /**
     * The YAML of the screens renamed in 0.6 (their bare names collided with
     * other modules'): earlier path => current path, below app-configs/cms.
     *
     * @var array<string, string>
     */
    public const RENAMED_CONFIGS = [
        'settings/settings-page.yml' => 'settings/cms-settings-page.yml',
        'details/article-detail.yml' => 'details/cms-article-detail.yml',
        'lists/articles-list.yml' => 'lists/cms-articles-list.yml',
    ];

    protected $signature = 'noerd:install-cms';

    protected $description = 'Install noerd CMS content and navigation';

    protected function getModuleName(): string
    {
        return 'CMS';
    }

    protected function getModuleKey(): string
    {
        return 'cms';
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
        // A project still on config/noerd_cms.php keeps it (read for the 0.6
        // line, LegacyCmsConfig) — config/cms.php is published only when the
        // project has neither.
        return LegacyCmsConfig::inUse() ? [] : [LegacyCmsConfig::FILE];
    }

    /**
     * Before anything is published: the host copies of renamed screens move to
     * their new names (keeping the project's edits) and the form YAML is
     * rewritten to the 0.6 vocabulary.
     */
    protected function runModuleUpdate(): int
    {
        $this->moveRenamedConfigs();
        $this->migrateFormYaml();

        return $this->runHasModuleUpdate();
    }

    /**
     * The website boilerplate is offered once, by the installation the user
     * started — and BEFORE the frontend build, which has to see its views.
     */
    protected function publishModuleExtras(bool $update): void
    {
        if (LegacyCmsConfig::inUse()) {
            $this->line('<comment>config/noerd_cms.php is deprecated: rename it to config/cms.php (same keys, read as cms.*). It is read for noerd/cms 0.6 only.</comment>');
        }

        if (! $update && ! ModuleInstallContext::isDependencyInstall()) {
            $this->installWebsiteIfNeeded();
        }
    }

    /**
     * Idempotent, on install AND update: every CMS tenant has its default
     * language, the form types follow app-configs/cms/forms (a form whose YAML
     * is gone turns inactive) and the quick-menu keeps the "To Website" button
     * — the shared writer replaces a same-component entry wholesale, so an
     * installation still carrying the removed `policy: canCms` gate migrates to
     * the `apps:` key, and a former bare component name is renamed to the
     * namespaced one. Only the INSTALL seeds the starter homepage.
     */
    protected function ensureModuleSetup(): void
    {
        $this->ensureDefaultLanguages();

        if (! $this->isUpdateCommand()) {
            $this->seedDefaultHomepage();
        }

        $this->syncFormTypes();
        $this->ensureWebsiteLinkButton();
    }

    /**
     * The starter homepage (page + cms_settings) of a tenant that has no page
     * and no settings row yet — a fresh installation starts with a usable page.
     */
    protected function seedDefaultHomepage(): void
    {
        try {
            $seeded = (new DefaultHomepageSeeder())->seedMissingHomepages();

            if ($seeded !== []) {
                $this->line(sprintf('<info>Starter homepage created for %d tenant(s).</info>', count($seeded)));
            }
        } catch (Exception $e) {
            $this->warn('Could not seed default homepage: ' . $e->getMessage());
        }
    }

    protected function ensureDefaultLanguages(): void
    {
        try {
            Tenant::query()
                ->whereHas('tenantApps', fn($query) => $query->where('name', 'CMS'))
                ->pluck('id')
                ->each(fn($tenantId) => TenantContext::run((int) $tenantId, fn() => CmsLanguage::ensureDefaultLanguageForTenant((int) $tenantId)));
        } catch (Exception $e) {
            $this->warn('Could not ensure the default CMS languages: ' . $e->getMessage());
        }
    }

    protected function syncFormTypes(): void
    {
        try {
            $results = app(FormTypeSyncService::class)->sync();

            $this->line(sprintf(
                '<info>Form types synced:</info> %d synced, %d unchanged, %d deactivated%s',
                $results['synced'],
                $results['skipped'],
                $results['deactivated'],
                $results['errors'] > 0 ? ", {$results['errors']} error(s) — run php artisan cms:sync-form-types -v" : '',
            ));
        } catch (Exception $e) {
            $this->warn('Could not sync the form types: ' . $e->getMessage());
        }
    }

    /**
     * Move the host copies of the screens renamed in 0.6 to their new names.
     * When the new file exists already, the earlier one is removed: its bare
     * name would be picked up by another module's screen of the same name.
     */
    protected function moveRenamedConfigs(): void
    {
        $base = base_path('app-configs/cms');

        foreach (self::RENAMED_CONFIGS as $from => $to) {
            $source = $base . '/' . $from;

            if (! is_file($source)) {
                continue;
            }

            if (is_file($base . '/' . $to)) {
                File::delete($source);
                $this->line("<comment>Removed app-configs/cms/{$from} (replaced by {$to}).</comment>");

                continue;
            }

            File::ensureDirectoryExists(dirname($base . '/' . $to));
            File::move($source, $base . '/' . $to);
            $this->line("<info>Moved app-configs/cms/{$from} to {$to}.</info>");
        }
    }

    protected function migrateFormYaml(): void
    {
        foreach (app(FormYamlMigrator::class)->migrate(MigrateFormYamlCommand::paths()) as $file) {
            $this->line('<info>Updated the form YAML to the current vocabulary:</info> ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file));
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
