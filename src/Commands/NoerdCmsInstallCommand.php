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

            // Publish the Claude Code skill so it is discoverable in .claude/skills
            $this->publishSkill();

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

    protected function getSnippetTitle(): string
    {
        return 'CMS';
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
     * Symlink the bundled Claude Code skill into the project's .claude/skills directory
     * so the harness picks it up. The source of truth stays in the cms module so the
     * skill is updated automatically when the module is upgraded.
     */
    private function publishSkill(): void
    {
        $source = realpath(__DIR__ . '/../../skills/cms-website-import');

        if ($source === false || ! is_dir($source)) {
            return;
        }

        $skillsDir = base_path('.claude/skills');
        $target = $skillsDir . '/cms-website-import';

        if (! is_dir($skillsDir) && ! mkdir($skillsDir, 0755, true) && ! is_dir($skillsDir)) {
            $this->warn('Could not create .claude/skills directory; skill not published.');

            return;
        }

        if (file_exists($target) || is_link($target)) {
            $this->line('<comment>Claude skill cms-website-import already published.</comment>');

            return;
        }

        $relativeSource = $this->relativePath(from: $skillsDir, to: $source);

        if (@symlink($relativeSource, $target)) {
            $this->line('<info>Published Claude skill:</info> .claude/skills/cms-website-import → ' . $relativeSource);

            return;
        }

        $this->warn('Symlink failed; copying skill files instead.');
        $this->copyDirectory($source, $target);
        $this->line('<info>Published Claude skill (copied):</info> .claude/skills/cms-website-import');
    }

    private function relativePath(string $from, string $to): string
    {
        $fromParts = explode('/', rtrim($from, '/'));
        $toParts = explode('/', rtrim($to, '/'));

        while ($fromParts && $toParts && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        return str_repeat('../', count($fromParts)) . implode('/', $toParts);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
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
