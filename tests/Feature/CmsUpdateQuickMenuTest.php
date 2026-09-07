<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

/*
 | The quick-menu writer is driven through the real update command against a
 | throwaway base path — the host project's app-configs/quick-menu.yml is
 | never touched.
 */
beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-cms-quick-menu-' . getmypid());

    File::deleteDirectory($this->hostPath);
    File::ensureDirectoryExists($this->hostPath . '/app-configs');
    File::ensureDirectoryExists($this->hostPath . '/config');
    File::put($this->hostPath . '/config/noerd.php', "<?php\n\nreturn [];\n");

    $this->app->setBasePath($this->hostPath);

    $this->quickMenuPath = $this->hostPath . '/app-configs/quick-menu.yml';
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
});

function runZzCmsQuickMenuUpdate(): void
{
    Artisan::call('noerd:update-cms', ['--force' => true, '--no-interaction' => true]);
}

it('creates quick-menu.yml with the website link button when the file does not exist', function (): void {
    expect(File::exists($this->quickMenuPath))->toBeFalse();

    runZzCmsQuickMenuUpdate();

    $config = Yaml::parseFile($this->quickMenuPath);
    expect($config['buttons'])->toHaveCount(1)
        ->and($config['buttons'][0])->toBe([
            'apps' => ['CMS'],
            'component' => 'quick-menu.website-link',
        ]);
});

it('migrates a legacy policy entry so the removed canCms gate no longer hides the button', function (): void {
    File::put($this->quickMenuPath, Yaml::dump([
        'buttons' => [
            ['policy' => 'canOther', 'component' => 'quick-menu.other-link'],
            ['policy' => 'canCms', 'component' => 'quick-menu.website-link'],
        ],
    ], 10, 2));

    runZzCmsQuickMenuUpdate();

    $config = Yaml::parseFile($this->quickMenuPath);
    expect($config['buttons'])->toHaveCount(2)
        ->and($config['buttons'][0])->toBe(['policy' => 'canOther', 'component' => 'quick-menu.other-link'])
        ->and($config['buttons'][1])->toBe([
            'apps' => ['CMS'],
            'component' => 'quick-menu.website-link',
        ]);
});

it('leaves an already migrated entry untouched', function (): void {
    $buttons = [
        ['apps' => ['CMS'], 'component' => 'quick-menu.website-link'],
    ];
    File::put($this->quickMenuPath, Yaml::dump(['buttons' => $buttons], 10, 2));

    runZzCmsQuickMenuUpdate();

    expect(Yaml::parseFile($this->quickMenuPath)['buttons'])->toBe($buttons);
});
