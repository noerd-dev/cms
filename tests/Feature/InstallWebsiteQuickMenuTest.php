<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Cms\Commands\InstallWebsiteBoilerplateCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Yaml\Yaml;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

/*
 | The website installer writes base_path('app-configs/quick-menu.yml'). The
 | tests therefore run against a THROWAWAY base path, so the real quick-menu
 | config of a host project is never touched.
 */
beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-cms-website-quick-menu-' . getmypid());

    File::deleteDirectory($this->hostPath);
    File::ensureDirectoryExists($this->hostPath . '/app-configs');

    $this->app->setBasePath($this->hostPath);
    $this->configPath = base_path('app-configs/quick-menu.yml');
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
});

/**
 * @param  array{buttons?: array<int, array{component?: string}>}  $config
 * @return array<int, array<string, mixed>>
 */
function zzCmsWebsiteLinkButtons(array $config): array
{
    return array_values(array_filter(
        $config['buttons'] ?? [],
        fn(array $button): bool => ($button['component'] ?? null) === 'quick-menu.website-link',
    ));
}

function zzCmsRunWebsiteQuickMenuInstall(): void
{
    $command = new InstallWebsiteBoilerplateCommand();
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput()));

    (new ReflectionMethod($command, 'installQuickMenuConfig'))->invoke($command);
}

it('creates quick-menu.yml with the website link button when the file does not exist', function (): void {
    zzCmsRunWebsiteQuickMenuInstall();

    expect(file_exists($this->configPath))->toBeTrue()
        ->and(zzCmsWebsiteLinkButtons(Yaml::parseFile($this->configPath)))->toHaveCount(1);

    // Re-running the install must not duplicate the entry.
    zzCmsRunWebsiteQuickMenuInstall();

    expect(zzCmsWebsiteLinkButtons(Yaml::parseFile($this->configPath)))->toHaveCount(1);
});

it('appends the website link button to an existing quick-menu.yml', function (): void {
    File::put($this->configPath, Yaml::dump(['buttons' => [['component' => 'quick-menu.other-button']]], 10, 2));

    zzCmsRunWebsiteQuickMenuInstall();

    $config = Yaml::parseFile($this->configPath);

    expect($config['buttons'])->toHaveCount(2)
        ->and($config['buttons'][0]['component'])->toBe('quick-menu.other-button')
        ->and(zzCmsWebsiteLinkButtons($config))->toHaveCount(1);
});

it('migrates a legacy policy-gated entry to the apps key', function (): void {
    File::put($this->configPath, Yaml::dump(['buttons' => [['policy' => 'canCms', 'component' => 'quick-menu.website-link']]], 10, 2));

    zzCmsRunWebsiteQuickMenuInstall();

    $buttons = zzCmsWebsiteLinkButtons(Yaml::parseFile($this->configPath));

    expect($buttons)->toHaveCount(1)
        ->and($buttons[0])->toBe(['apps' => ['CMS'], 'component' => 'quick-menu.website-link']);
});

it('handles an existing file without a buttons key', function (): void {
    File::put($this->configPath, Yaml::dump(['other' => 'value'], 10, 2));

    zzCmsRunWebsiteQuickMenuInstall();

    $config = Yaml::parseFile($this->configPath);

    expect($config['other'])->toBe('value')
        ->and(zzCmsWebsiteLinkButtons($config))->toHaveCount(1);
});
