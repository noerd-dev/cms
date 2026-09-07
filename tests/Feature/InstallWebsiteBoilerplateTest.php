<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

/*
 | The installer copies the boilerplate into base_path('app-modules/website')
 | and touches composer.json — everything runs against a THROWAWAY base path.
 */
beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-cms-website-install-' . getmypid());

    File::deleteDirectory($this->hostPath);
    File::ensureDirectoryExists($this->hostPath . '/app-configs');
    File::put($this->hostPath . '/composer.json', json_encode(['name' => 'zz/host', 'require' => []], JSON_PRETTY_PRINT));

    $this->app->setBasePath($this->hostPath);
    $this->targetDir = base_path('app-modules/website');
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
});

it('copies the boilerplate and registers the path repository without shelling out', function (): void {
    $exitCode = Artisan::call('noerd:install-website');

    expect($exitCode)->toBe(0)
        ->and(file_exists($this->targetDir . '/composer.json'))->toBeTrue()
        ->and(is_dir($this->targetDir . '/resources/views/components/elements'))->toBeTrue()
        ->and(file_exists($this->targetDir . '/src/Providers/WebsiteServiceProvider.php'))->toBeTrue();

    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
    expect($composer['repositories'])->toContain(['type' => 'path', 'url' => 'app-modules/*']);

    // The website link lands in the quick menu.
    expect(file_exists(base_path('app-configs/quick-menu.yml')))->toBeTrue();
});

it('overwrites an existing copy with --force', function (): void {
    Artisan::call('noerd:install-website');
    File::put($this->targetDir . '/zz-local-change.txt', 'local');

    expect(Artisan::call('noerd:install-website', ['--force' => true]))->toBe(0)
        ->and(file_exists($this->targetDir . '/zz-local-change.txt'))->toBeFalse()
        ->and(file_exists($this->targetDir . '/composer.json'))->toBeTrue();
});

it('refuses to replace a website module that is its own git repository', function (): void {
    File::ensureDirectoryExists($this->targetDir . '/.git');
    File::put($this->targetDir . '/keep.txt', 'keep');

    expect(Artisan::call('noerd:install-website', ['--force' => true]))->toBe(1)
        ->and(file_exists($this->targetDir . '/keep.txt'))->toBeTrue();
});
