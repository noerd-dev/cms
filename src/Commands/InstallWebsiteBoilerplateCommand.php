<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

class InstallWebsiteBoilerplateCommand extends Command
{
    protected $signature = 'noerd:install-website {--force : Overwrite existing files without asking}';

    protected $description = 'Install website boilerplate from CMS module to app-modules/website';

    public function handle(): int
    {
        $this->info('Installing website boilerplate...');

        $sourceDir = dirname(__DIR__, 2) . '/website-boilerplate';
        $targetDir = base_path('app-modules/website');

        if (! is_dir($sourceDir)) {
            $this->error("Website boilerplate not found: {$sourceDir}");

            return 1;
        }

        // Never delete a target that is its own git repository — it may hold
        // unpushed customizations. This guard also applies under --force.
        if (is_dir($targetDir . '/.git')) {
            $this->error("Directory {$targetDir} is a git repository. Remove it manually before reinstalling the boilerplate.");

            return self::FAILURE;
        }

        // Check if target already exists and not forcing
        if (is_dir($targetDir) && ! $this->option('force')) {
            if (! $this->confirm("Directory {$targetDir} already exists. Do you want to overwrite it?")) {
                $this->info('Installation cancelled.');

                return self::INVALID;
            }
        }

        try {
            // Remove existing target directory if it exists
            if (is_dir($targetDir)) {
                $this->line('<comment>Removing existing directory...</comment>');
                File::deleteDirectory($targetDir);
            }

            // Copy boilerplate to target
            $results = $this->copyDirectoryContents($sourceDir, $targetDir);

            $this->displaySummary($results);

            // Register the module
            $this->registerModule();

            // Ensure quick-menu config contains the website link button
            $this->installQuickMenuConfig();

            $this->info('Website boilerplate successfully installed!');
            $this->line('');

            if ($this->confirm('Would you like to run migrations now?', true)) {
                Artisan::call('migrate', ['--force' => true], $this->output);
            }

            $this->line('<info>Next steps:</info>');
            $this->line('- Review the generated files in app-modules/website');
            $this->line('- Customize the module according to your needs');
            $this->line('- Run composer dump-autoload to refresh autoloading');

            return 0;
        } catch (Exception $e) {
            $this->error('Error installing website boilerplate: ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * Copy directory contents recursively
     */
    private function copyDirectoryContents(string $sourceDir, string $targetDir): array
    {
        $results = [
            'created_dirs' => 0,
            'copied_files' => 0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = mb_substr($sourcePath, mb_strlen($sourceDir) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                // Create directory if it doesn't exist
                if (! is_dir($targetPath)) {
                    if (! mkdir($targetPath, 0755, true)) {
                        throw new Exception("Failed to create directory: {$targetPath}");
                    }
                    $this->line("<info>Created directory:</info> {$relativePath}");
                    $results['created_dirs']++;
                }
            } else {
                // Create parent directory if needed
                $parentDir = dirname($targetPath);
                if (! is_dir($parentDir)) {
                    mkdir($parentDir, 0755, true);
                }

                // Copy file
                if (! copy($sourcePath, $targetPath)) {
                    throw new Exception("Failed to copy file: {$sourcePath} to {$targetPath}");
                }

                $this->line("<info>Copied:</info> {$relativePath}");
                $results['copied_files']++;
            }
        }

        return $results;
    }

    /**
     * Register the website module
     */
    private function registerModule(): void
    {
        $this->line('');
        $this->info('Registering website module...');

        try {
            // Make sure composer can see the copied module, then leave the
            // composer run itself to the operator — shelling out to composer
            // from inside artisan is fragile (PHP binary, memory limit, TTY).
            $this->ensurePathRepository();

            // Clear Laravel's cached services to ensure service provider discovery
            $this->line('<comment>Clearing Laravel caches...</comment>');
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            // Clear the cached services to force re-discovery of service providers
            $servicesPath = base_path('bootstrap/cache/services.php');
            if (file_exists($servicesPath)) {
                unlink($servicesPath);
                $this->line('<info>Cleared cached services file.</info>');
            }

            $this->line('');
            $this->info('Finish the registration with:');
            $this->line('  composer require noerd/website');
        } catch (Exception $e) {
            $this->warn('Module registration may need manual intervention: ' . $e->getMessage());
        }
    }

    /**
     * The copied module is required through a path repository covering
     * app-modules/*. Add one to composer.json when missing, so the command also
     * works on hosts installed from a package registry.
     */
    private function ensurePathRepository(): void
    {
        $composerPath = base_path('composer.json');
        $composerJson = json_decode((string) file_get_contents($composerPath), true) ?? [];
        $hasPathRepository = collect($composerJson['repositories'] ?? [])
            ->contains(fn($repository) => ($repository['type'] ?? null) === 'path'
                && str_starts_with((string) ($repository['url'] ?? ''), 'app-modules'));

        if ($hasPathRepository) {
            return;
        }

        $this->line('<comment>Adding app-modules path repository to composer.json...</comment>');
        $composerJson['repositories'] = [
            ...array_values($composerJson['repositories'] ?? []),
            ['type' => 'path', 'url' => 'app-modules/*'],
        ];

        file_put_contents(
            $composerPath,
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );
    }

    /**
     * Ensure the quick-menu config contains the website link button.
     */
    private function installQuickMenuConfig(): void
    {
        $configPath = base_path('app-configs/quick-menu.yml');
        $button = ['apps' => ['CMS'], 'component' => 'quick-menu.website-link'];

        if (file_exists($configPath)) {
            $config = Yaml::parse(file_get_contents($configPath)) ?? [];
            $buttons = $config['buttons'] ?? [];

            // Match on the component and replace wholesale — a legacy entry
            // still carrying the removed `policy:` gate migrates on re-install.
            foreach ($buttons as $i => $existing) {
                if (($existing['component'] ?? null) === $button['component']) {
                    if ($existing === $button) {
                        $this->line('<comment>Quick-menu already contains the website link button.</comment>');

                        return;
                    }

                    $buttons[$i] = $button;
                    $config['buttons'] = $buttons;
                    file_put_contents($configPath, Yaml::dump($config, 10, 2));
                    $this->line('<info>Quick-menu config updated:</info> app-configs/quick-menu.yml');

                    return;
                }
            }

            $config['buttons'] = [...$buttons, $button];
        } else {
            File::ensureDirectoryExists(dirname($configPath));
            $config = ['buttons' => [$button]];
        }

        file_put_contents($configPath, Yaml::dump($config, 10, 2));
        $this->line('<info>Quick-menu config updated:</info> app-configs/quick-menu.yml');
    }

    /**
     * Display summary of operations
     */
    private function displaySummary(array $results): void
    {
        $this->line('');
        $this->info('Installation Summary:');
        $this->table(
            ['Operation', 'Count'],
            [
                ['Directories created', $results['created_dirs']],
                ['Files copied', $results['copied_files']],
            ],
        );
    }
}
