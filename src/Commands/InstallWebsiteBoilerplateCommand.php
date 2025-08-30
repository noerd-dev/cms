<?php

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class InstallWebsiteBoilerplateCommand extends Command
{
    protected $signature = 'cms:install-website {--force : Overwrite existing files without asking}';

    protected $description = 'Install website boilerplate from CMS module to app-modules/website';

    public function handle(): int
    {
        $this->info('Installing website boilerplate...');

        $sourceDir = base_path('app-modules/cms/website-boilerplate');
        $targetDir = base_path('app-modules/website');

        if (!is_dir($sourceDir)) {
            $this->error("Website boilerplate not found: {$sourceDir}");
            return 1;
        }

        // Check if target already exists and not forcing
        if (is_dir($targetDir) && !$this->option('force')) {
            if (!$this->confirm("Directory {$targetDir} already exists. Do you want to overwrite it?")) {
                $this->info('Installation cancelled.');
                return 0;
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

            $this->info('Website boilerplate successfully installed!');
            $this->line('');
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
                if (!is_dir($targetPath)) {
                    if (!mkdir($targetPath, 0755, true)) {
                        throw new Exception("Failed to create directory: {$targetPath}");
                    }
                    $this->line("<info>Created directory:</info> {$relativePath}");
                    $results['created_dirs']++;
                }
            } else {
                // Create parent directory if needed
                $parentDir = dirname($targetPath);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0755, true);
                }

                // Copy file
                if (!copy($sourcePath, $targetPath)) {
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
            // Run composer dump-autoload to ensure the module is discoverable
            $this->line('<comment>Running composer dump-autoload...</comment>');
            exec('cd ' . base_path() . ' && composer dump-autoload', $output, $returnCode);

            if ($returnCode !== 0) {
                $this->warn('Failed to run composer dump-autoload automatically. Please run it manually.');
            } else {
                $this->line('<info>Autoloader refreshed successfully.</info>');
            }

            // The module should be auto-discovered by Laravel's service provider discovery
            $this->line('<info>Module registered successfully.</info>');
        } catch (Exception $e) {
            $this->warn('Module registration may need manual intervention: ' . $e->getMessage());
        }
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
