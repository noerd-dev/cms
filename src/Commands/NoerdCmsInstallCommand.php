<?php

namespace Noerd\Cms\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NoerdCmsInstallCommand extends Command
{
    protected $signature = 'noerd:install-cms {--force : Overwrite existing files without asking} {--without-website : Skip automatic website module installation}';

    protected $description = 'Install noerd cms content to the local content directory';

    public function handle()
    {
        $this->info('Installing noerd content...');

        $sourceDir = base_path('vendor/noerd/cms/content');
        $targetDir = base_path('content');

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return 1;
        }

        // Create target directory if it doesn't exist
        if (! is_dir($targetDir)) {

            if (! mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create target directory: {$targetDir}");

                return 1;
            }

            $this->info("Created target directory: {$targetDir}");
        }

        try {
            $results = $this->copyDirectoryContents($sourceDir, $targetDir);

            // Ensure lists are copied explicitly to content/lists
            $listsSource = $sourceDir.DIRECTORY_SEPARATOR.'lists';
            $listsTarget = $targetDir.DIRECTORY_SEPARATOR.'lists';
            if (is_dir($listsSource)) {
                $listResults = $this->copyDirectoryContents($listsSource, $listsTarget);
                $results = $this->mergeResults($results, $listResults);
            }

            $this->displaySummary($results);

            // Register the CMS module
            $this->registerModule();

            // Install website module if it doesn't exist
            $this->installWebsiteIfNeeded();

            $this->info('Noerd CMS content successfully installed!');

            return 0;
        } catch (Exception $e) {
            $this->error('Error installing noerd content: '.$e->getMessage());

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
            'skipped_files' => 0,
            'overwritten_files' => 0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = mb_substr($sourcePath, mb_strlen($sourceDir) + 1);
            $targetPath = $targetDir.DIRECTORY_SEPARATOR.$relativePath;

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
                // Check if file already exists
                if (file_exists($targetPath)) {
                    if (! $this->option('force')) {

                        $choice = $this->choice(
                            "File already exists: {$relativePath}. What do you want to do?",
                            ['skip', 'overwrite', 'overwrite-all'],
                            'skip',
                        );

                        if ($choice === 'skip') {
                            $this->line("<comment>Skipped:</comment> {$relativePath}");
                            $results['skipped_files']++;

                            continue;
                        }
                        if ($choice === 'overwrite-all') {
                            // Set force option for remaining files
                            $this->input->setOption('force', true);
                        }
                    }

                    $this->line("<comment>Overwriting:</comment> {$relativePath}");
                    $results['overwritten_files']++;
                } else {
                    $this->line("<info>Copying:</info> {$relativePath}");
                    $results['copied_files']++;
                }

                if (! copy($sourcePath, $targetPath)) {
                    throw new Exception("Failed to copy file: {$sourcePath} to {$targetPath}");
                }

            }
        }

        return $results;
    }

    private function mergeResults(array $a, array $b): array
    {
        foreach (['created_dirs', 'copied_files', 'skipped_files', 'overwritten_files'] as $key) {
            $a[$key] = ($a[$key] ?? 0) + ($b[$key] ?? 0);
        }

        return $a;
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
                ['Files overwritten', $results['overwritten_files']],
                ['Files skipped', $results['skipped_files']],
            ],
        );
    }

    /**
     * Register the CMS module with Composer
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
            $this->warn('Module registration failed: '.$e->getMessage());
        }
    }

    /**
     * Install website module if it doesn't already exist
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
            $this->warn('Failed to install website module: '.$e->getMessage());
        }
    }
}
