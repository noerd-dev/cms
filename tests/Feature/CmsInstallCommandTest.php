<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Models\TenantApp;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

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
    // A registered MEDIA app and an existing website module short-circuit the two
    // follow-up installers, so this test covers the CMS only.
    File::put($this->hostPath . '/config/filesystems.php', "<?php\n\nreturn ['disks' => ['media' => []]];\n");
    File::ensureDirectoryExists($this->hostPath . '/app-modules/website');

    $this->mediaApp = TenantApp::firstOrCreate(
        ['name' => 'MEDIA'],
        [
            'title' => 'Media',
            'icon' => 'media::icons.app',
            'route' => 'media.dashboard',
            'is_active' => true,
        ],
    );

    // No CMS row: the fresh-install branch (a registered app diverts to update).
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
        ->and(File::exists($this->hostPath . '/config/cms.php'))->toBeTrue();
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

it('gives the media app to every tenant the cms is assigned to', function (): void {
    // Image fields store a media id and are picked from the media library: a
    // tenant running the CMS has to run MEDIA too — in the SAME prompt, so the
    // media installer never asks a tenant question of its own.
    $tenant = Noerd\Models\Tenant::factory()->create(['name' => 'Zz Cms Tenant']);

    $this->artisan('noerd:install-cms', ['--force' => true])
        ->expectsQuestion('App title', 'CMS')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'yes')
        ->expectsQuestion("Which tenants should 'CMS' be assigned to?", [$tenant->id])
        ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    expect($this->mediaApp->fresh()->tenants()->pluck('tenants.id')->all())->toContain($tenant->id);
});

it('installs a missing media module first — silently, as a dependency', function (): void {
    $this->mediaApp->delete();
    $tenant = Noerd\Models\Tenant::factory()->create(['name' => 'Zz Cms Media Tenant']);

    // Every question below belongs to the CMS: media is installed with its
    // defaults and asks nothing (an unexpected prompt would fail the run).
    $this->artisan('noerd:install-cms', ['--force' => true])
        ->expectsOutputToContain('CMS requires MEDIA, running noerd:install-media')
        ->expectsQuestion('App title', 'CMS')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'yes')
        ->expectsQuestion("Which tenants should 'CMS' be assigned to?", [$tenant->id])
        ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    $media = TenantApp::where('name', 'MEDIA')->first();

    expect($media)->not->toBeNull()
        ->and($media->tenants()->pluck('tenants.id')->all())->toContain($tenant->id)
        ->and(File::exists($this->hostPath . '/app-configs/media/navigation.yml'))->toBeTrue();
});

it('seeds the starter homepage on install for a fresh tenant — and the update never does', function (): void {
    $tenant = Noerd\Models\Tenant::factory()->create(['name' => 'Zz Cms Fresh Tenant']);

    $this->artisan('noerd:install-cms', ['--force' => true])
        ->expectsQuestion('App title', 'CMS')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'yes')
        ->expectsQuestion("Which tenants should 'CMS' be assigned to?", [$tenant->id])
        ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    $setting = Noerd\Cms\Models\CmsSetting::forTenant($tenant->id);
    expect($setting->exists)->toBeTrue()
        ->and($setting->homepage_page_id)->not->toBeNull()
        // The app assignment created the tenant's default website language.
        ->and(Noerd\Cms\Models\CmsLanguage::query()->ofTenant($tenant->id)->where('is_default', true)->exists())->toBeTrue();

    // An admin clears the homepage and deletes the page: the update leaves it so.
    Noerd\Cms\Models\Page::query()->ofTenant($tenant->id)->get()->each->delete();
    Noerd\Cms\Models\CmsSetting::updateForTenant(['homepage_page_id' => null], $tenant->id);

    $this->artisan('noerd:update-cms', ['--force' => true])->assertExitCode(0);

    expect(Noerd\Cms\Models\Page::query()->ofTenant($tenant->id)->count())->toBe(0)
        ->and(Noerd\Cms\Models\CmsSetting::forTenant($tenant->id)->homepage_page_id)->toBeNull();
});

it('moves the host copies of the renamed screens and rewrites the form yaml on update', function (): void {
    runZzCmsInstall($this)->assertExitCode(0);

    $configs = $this->hostPath . '/app-configs/cms';
    File::delete($configs . '/settings/cms-settings-page.yml');
    File::put($configs . '/settings/settings-page.yml', "title: Project Settings\nfields: []\n");
    File::put($configs . '/lists/articles-list.yml', "title: Old\ncolumns: []\n");
    File::put($configs . '/forms/zz-legacy.yml', "key: zz-legacy\ntitle: Legacy\nsend_email: true\nemail_subject: Hi\nfields:\n  - name: email\n    error_messages:\n      required: Required\n");

    expect(Illuminate\Support\Facades\Artisan::call('noerd:update-cms', ['--no-interaction' => true]))->toBe(0)
        ->and(Illuminate\Support\Facades\Artisan::output())
        ->toContain('Moved app-configs/cms/settings/settings-page.yml to settings/cms-settings-page.yml')
        ->toContain('Removed app-configs/cms/lists/articles-list.yml');

    expect(File::exists($configs . '/settings/settings-page.yml'))->toBeFalse()
        // Moved, not replaced: the project's own version survives.
        ->and(File::get($configs . '/settings/cms-settings-page.yml'))->toContain('Project Settings')
        ->and(File::exists($configs . '/lists/articles-list.yml'))->toBeFalse()
        ->and(File::exists($configs . '/lists/cms-articles-list.yml'))->toBeTrue();

    $legacy = File::get($configs . '/forms/zz-legacy.yml');
    expect($legacy)->toContain('sendEmail: true')
        ->toContain('sendConfirmation: true')
        ->toContain('confirmationSubject: Hi')
        ->toContain('errorMessages:')
        ->not->toContain('send_email');
});

it('keeps a project on config/noerd_cms.php and publishes no config/cms.php beside it', function (): void {
    File::put($this->hostPath . '/config/noerd_cms.php', "<?php\n\nreturn ['website_url' => 'https://legacy.test'];\n");

    runZzCmsInstall($this)->assertExitCode(0);

    expect(File::exists($this->hostPath . '/config/cms.php'))->toBeFalse()
        ->and(File::get($this->hostPath . '/config/noerd_cms.php'))->toContain('legacy.test');
});


it('reads a project\'s earlier config/noerd_cms.php over the cms defaults for the 0.6 line', function (): void {
    File::put($this->hostPath . '/config/noerd_cms.php', "<?php\n\nreturn ['website_url' => 'https://legacy.test'];\n");
    config(['noerd_cms' => ['website_url' => 'https://legacy.test'], 'cms.website_url' => '']);

    Noerd\Cms\Support\LegacyCmsConfig::apply($this->app);

    expect(config('cms.website_url'))->toBe('https://legacy.test')
        // The defaults the earlier file does not name stay.
        ->and(config('cms.collection_field_types'))->toHaveKey('elementCollection');

    // A project with config/cms.php of its own is not touched by the earlier file.
    File::put($this->hostPath . '/config/cms.php', "<?php\n\nreturn [];\n");
    config(['cms.website_url' => 'https://new.test']);
    Noerd\Cms\Support\LegacyCmsConfig::apply($this->app);

    expect(config('cms.website_url'))->toBe('https://new.test');
});
