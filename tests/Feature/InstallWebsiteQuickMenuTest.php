<?php

use Illuminate\Support\Facades\File;
use Noerd\Cms\Commands\InstallWebsiteBoilerplateCommand;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);

it('creates quick-menu.yml with website link button when file does not exist', function (): void {
    $path = base_path('app-configs/quick-menu.yml');
    $existed = file_exists($path);
    $original = $existed ? file_get_contents($path) : null;

    try {
        if (file_exists($path)) {
            unlink($path);
        }

        $command = new InstallWebsiteBoilerplateCommand();
        $method = new ReflectionMethod($command, 'installQuickMenuConfig');
        $command->setLaravel(app());
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        ));
        $method->invoke($command);

        expect(file_exists($path))->toBeTrue();

        $config = Yaml::parseFile($path);
        expect($config)->toHaveKey('buttons')
            ->and($config['buttons'])->toHaveCount(1)
            ->and($config['buttons'][0])->toBe([
                'policy' => 'canCms',
                'component' => 'quick-menu.website-link',
            ]);
    } finally {
        if ($existed) {
            file_put_contents($path, $original);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
});

it('appends website link button to existing quick-menu.yml', function (): void {
    $path = base_path('app-configs/quick-menu.yml');
    $existed = file_exists($path);
    $original = $existed ? file_get_contents($path) : null;

    try {
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, Yaml::dump([
            'buttons' => [
                ['policy' => 'canOther', 'component' => 'quick-menu.other-link'],
            ],
        ], 10, 2));

        $command = new InstallWebsiteBoilerplateCommand();
        $method = new ReflectionMethod($command, 'installQuickMenuConfig');
        $command->setLaravel(app());
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        ));
        $method->invoke($command);

        $config = Yaml::parseFile($path);
        expect($config['buttons'])->toHaveCount(2)
            ->and($config['buttons'][0]['component'])->toBe('quick-menu.other-link')
            ->and($config['buttons'][1])->toBe([
                'policy' => 'canCms',
                'component' => 'quick-menu.website-link',
            ]);
    } finally {
        if ($existed) {
            file_put_contents($path, $original);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
});

it('does not duplicate website link button if already present', function (): void {
    $path = base_path('app-configs/quick-menu.yml');
    $existed = file_exists($path);
    $original = $existed ? file_get_contents($path) : null;

    try {
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, Yaml::dump([
            'buttons' => [
                ['policy' => 'canCms', 'component' => 'quick-menu.website-link'],
            ],
        ], 10, 2));

        $command = new InstallWebsiteBoilerplateCommand();
        $method = new ReflectionMethod($command, 'installQuickMenuConfig');
        $command->setLaravel(app());
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        ));
        $method->invoke($command);

        $config = Yaml::parseFile($path);
        expect($config['buttons'])->toHaveCount(1);
    } finally {
        if ($existed) {
            file_put_contents($path, $original);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
});

it('handles existing file with missing buttons key', function (): void {
    $path = base_path('app-configs/quick-menu.yml');
    $existed = file_exists($path);
    $original = $existed ? file_get_contents($path) : null;

    try {
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, Yaml::dump(['other_key' => 'value'], 10, 2));

        $command = new InstallWebsiteBoilerplateCommand();
        $method = new ReflectionMethod($command, 'installQuickMenuConfig');
        $command->setLaravel(app());
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        ));
        $method->invoke($command);

        $config = Yaml::parseFile($path);
        expect($config)->toHaveKey('buttons')
            ->and($config['buttons'])->toHaveCount(1)
            ->and($config['buttons'][0])->toBe([
                'policy' => 'canCms',
                'component' => 'quick-menu.website-link',
            ])
            ->and($config)->toHaveKey('other_key');
    } finally {
        if ($existed) {
            file_put_contents($path, $original);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
});
