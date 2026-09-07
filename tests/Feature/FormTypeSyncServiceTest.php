<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Noerd\Cms\Models\FormType;
use Noerd\Cms\Services\FormTypeSyncService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

/*
 | The sync reads base_path('app-configs/cms/forms'). The tests therefore run
 | against a THROWAWAY base path, so the host's real app-configs are never
 | touched — no rename, no restore, nothing to lose when a run is aborted.
 */
beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-cms-form-sync');

    File::deleteDirectory($this->hostPath);
    File::ensureDirectoryExists($this->hostPath . '/app-configs/cms/forms');

    $this->app->setBasePath($this->hostPath);
    $this->formsPath = base_path('app-configs/cms/forms');
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
});

function zzWriteFormFixtureYaml(string $formsPath, string $filename, string $yaml): string
{
    $file = $formsPath . '/' . $filename;
    File::put($file, $yaml);

    return $file;
}

it('creates form type rows for every tenant on the initial sync', function (): void {
    $tenantA = $this->createCmsTenant();
    $tenantB = $this->createCmsTenant();

    zzWriteFormFixtureYaml($this->formsPath, 'fixture-contact.yml', <<<'YAML'
key: fixture-contact
title: Fixture Contact
send_email: true
notification_email: forms@example.com
YAML);

    $exitCode = Artisan::call('cms:sync-form-types');

    expect($exitCode)->toBe(0);

    foreach ([$tenantA, $tenantB] as $tenant) {
        $this->assertDatabaseHas('cms_form_types', [
            'tenant_id' => $tenant->id,
            'key' => 'fixture-contact',
            'title' => 'Fixture Contact',
            'send_email' => 1,
            'notification_email' => 'forms@example.com',
        ]);
    }

    expect(FormType::withoutGlobalScopes()->where('key', 'fixture-contact')->count())->toBe(2);
});

it('skips the second sync when the file mtime has not changed', function (): void {
    $this->createCmsTenant();

    zzWriteFormFixtureYaml($this->formsPath, 'fixture-skip.yml', <<<'YAML'
key: fixture-skip
title: Fixture Skip
YAML);

    $service = new FormTypeSyncService();

    $first = $service->sync();
    expect($first['synced'])->toBe(1);
    expect($first['skipped'])->toBe(0);

    $syncedAt = FormType::withoutGlobalScopes()->where('key', 'fixture-skip')->firstOrFail()->yml_synced_at;

    $second = $service->sync();
    expect($second['synced'])->toBe(0);
    expect($second['skipped'])->toBe(1);

    $freshSyncedAt = FormType::withoutGlobalScopes()->where('key', 'fixture-skip')->firstOrFail()->yml_synced_at;
    expect($freshSyncedAt->equalTo($syncedAt))->toBeTrue();
});

it('re-syncs when the yaml file is touched', function (): void {
    $this->createCmsTenant();

    $file = zzWriteFormFixtureYaml($this->formsPath, 'fixture-touch.yml', <<<'YAML'
key: fixture-touch
title: Fixture Touch
YAML);

    $service = new FormTypeSyncService();
    $service->sync();

    // yml_synced_at has second precision — the new mtime must be strictly
    // greater than the recorded sync time for the change to register.
    touch($file, time() + 5);
    clearstatcache();

    $second = $service->sync();

    expect($second['synced'])->toBe(1);
    expect($second['skipped'])->toBe(0);
});

it('force re-syncs files regardless of mtime', function (): void {
    $this->createCmsTenant();

    zzWriteFormFixtureYaml($this->formsPath, 'fixture-force.yml', <<<'YAML'
key: fixture-force
title: Fixture Force
YAML);

    $service = new FormTypeSyncService();
    $service->sync();

    $forced = $service->sync(null, true);

    expect($forced['synced'])->toBe(1);
    expect($forced['skipped'])->toBe(0);

    // The --force flag drives the same path through the artisan command.
    expect(Artisan::call('cms:sync-form-types', ['--force' => true]))->toBe(0);
});

it('reports an error for a yaml file without a key', function (): void {
    $this->createCmsTenant();

    zzWriteFormFixtureYaml($this->formsPath, 'fixture-broken.yml', <<<'YAML'
title: Broken Fixture Without Key
YAML);

    $service = new FormTypeSyncService();
    $result = $service->sync();

    expect($result['errors'])->toBe(1);
    expect($result['synced'])->toBe(0);
    expect(implode(' ', $result['messages']))->toContain("missing 'key'");
    expect(FormType::withoutGlobalScopes()->count())->toBe(0);

    // The command exits with failure when the sync reported errors.
    expect(Artisan::call('cms:sync-form-types'))->toBe(1);
});

it('handles a missing or empty forms directory gracefully', function (): void {
    $service = new FormTypeSyncService();

    File::deleteDirectory($this->formsPath);

    $result = $service->sync();
    expect($result['synced'])->toBe(0);
    expect($result['errors'])->toBe(0);
    expect(implode(' ', $result['messages']))->toContain('Forms directory not found');

    // An existing but empty directory is reported as "no files" without errors.
    File::makeDirectory($this->formsPath, 0755, true);

    $result = $service->sync();
    expect($result['synced'])->toBe(0);
    expect($result['errors'])->toBe(0);
    expect(implode(' ', $result['messages']))->toContain('No YML files found');
});

it('does not duplicate rows when syncing repeatedly', function (): void {
    $tenant = $this->createCmsTenant();

    zzWriteFormFixtureYaml($this->formsPath, 'fixture-idempotent.yml', <<<'YAML'
key: fixture-idempotent
title: Fixture Idempotent
YAML);

    $service = new FormTypeSyncService();
    $service->sync();
    $service->sync(null, true);
    $service->sync(null, true);

    expect(
        FormType::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'fixture-idempotent')
            ->count(),
    )->toBe(1);
});
