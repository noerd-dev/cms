<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Models\TenantApp;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 | The installer writes into base_path() (app-configs, config, .claude/skills)
 | and registers a tenant_apps row, so the real command runs against a
 | throwaway base path on a refreshed database.
 */
beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-cms-install-' . getmypid());

    File::deleteDirectory($this->hostPath);
    File::ensureDirectoryExists($this->hostPath . '/config');
    File::ensureDirectoryExists($this->hostPath . '/database/migrations');
    // The module installers refuse to run until noerd itself is installed.
    File::put($this->hostPath . '/config/noerd.php', "<?php\n\nreturn [];\n");
    // A configured media disk and an existing website module short-circuit the
    // two optional follow-up installers, so this test covers the CMS only.
    File::put($this->hostPath . '/config/filesystems.php', "<?php\n\nreturn ['disks' => ['media' => []]];\n");
    File::ensureDirectoryExists($this->hostPath . '/app-modules/website');

    // The module migration pre-registers the CMS tenant app so a plain
    // `php artisan migrate` works; drop it to reach the fresh-install branch.
    TenantApp::where('name', 'CMS')->delete();

    $this->app->setBasePath($this->hostPath);
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
});

function runZzCmsInstall(object $test): Illuminate\Testing\PendingCommand
{
    return $test->artisan('noerd:install-cms', ['--force' => true])
        ->expectsConfirmation('Should CMS be installed as a hidden app (not shown in main navigation)?', 'no')
        ->expectsQuestion('App title', 'CMS')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
        ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no');
}

it('registers the tenant app and publishes the cms app-configs and config file', function (): void {
    runZzCmsInstall($this)->assertExitCode(0);

    $app = TenantApp::where('name', 'CMS')->first();
    expect($app)->not->toBeNull()
        ->and($app->route)->toBe('cms.dashboard')
        ->and($app->is_active)->toBeTrue();

    expect(File::exists($this->hostPath . '/app-configs/cms/navigation.yml'))->toBeTrue()
        ->and(File::files($this->hostPath . '/app-configs/cms/lists'))->not->toBeEmpty()
        ->and(File::files($this->hostPath . '/app-configs/cms/details'))->not->toBeEmpty()
        // getAdditionalSubdirectories() adds the form YAML folder.
        ->and(File::files($this->hostPath . '/app-configs/cms/forms'))->not->toBeEmpty()
        ->and(File::exists($this->hostPath . '/config/noerd_cms.php'))->toBeTrue();
});

it('stays idempotent when the install is run a second time', function (): void {
    runZzCmsInstall($this)->assertExitCode(0);

    $this->artisan('noerd:install-cms', ['--force' => true])
        ->expectsOutputToContain('is already installed. Running update instead...')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
        ->assertExitCode(0);

    expect(TenantApp::where('name', 'CMS')->count())->toBe(1);
    expect(File::exists($this->hostPath . '/app-configs/cms/navigation.yml'))->toBeTrue();
});
