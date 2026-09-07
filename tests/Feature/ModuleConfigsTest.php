<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

uses(Noerd\Cms\Tests\TestCase::class);

$moduleConfigs = dirname(__DIR__, 2) . '/app-configs/cms';

/**
 * Every YAML the module ships, relative to app-configs/cms.
 *
 * @return array<int, string>
 */
function zzCmsModuleYamlFiles(string $moduleConfigs): array
{
    return collect(File::allFiles($moduleConfigs))
        ->filter(fn($file): bool => $file->getExtension() === 'yml')
        ->map(fn($file): string => $file->getRelativePathname())
        ->sort()
        ->values()
        ->all();
}

it('ships a list and a detail YAML for every list and detail component', function () use ($moduleConfigs): void {
    $components = collect(File::files(dirname(__DIR__, 2) . '/resources/views/components'))
        ->map(fn($file): string => str_replace('.blade.php', '', $file->getFilename()));

    $missing = $components
        ->filter(fn(string $name): bool => str_ends_with($name, '-list') || str_ends_with($name, '-detail'))
        // Runtime layouts (repository/element schema) and the read-only submission view.
        ->reject(fn(string $name): bool => in_array($name, ['collection-definitions-list', 'collection-entries-list', 'element-page-detail', 'element-collection-row-detail', 'form-request-detail'], true))
        ->reject(fn(string $name): bool => file_exists($moduleConfigs . (str_ends_with($name, '-list') ? '/lists/' : '/details/') . $name . '.yml'))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('keeps the installed project copy in sync with the module YAML', function () use ($moduleConfigs): void {
    $projectConfigs = base_path('app-configs/cms');

    if (! is_dir($projectConfigs) || realpath($projectConfigs) === realpath($moduleConfigs)) {
        // Standalone (testbench): no installed copy to compare against.
        expect(zzCmsModuleYamlFiles($moduleConfigs))->not->toBe([]);

        return;
    }

    $outOfSync = collect(zzCmsModuleYamlFiles($moduleConfigs))
        ->reject(fn(string $file): bool => file_exists($projectConfigs . '/' . $file)
            && Yaml::parseFile($projectConfigs . '/' . $file) === Yaml::parseFile($moduleConfigs . '/' . $file))
        ->values()
        ->all();

    expect($outOfSync)->toBe([]);
});
