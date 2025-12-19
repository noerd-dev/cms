<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);

it('ensures all setup navigation routes exist', function (): void {
    $routes = extractNavigationRoutes('setup');

    expect($routes)->not->toBeEmpty('No routes found in setup/navigation.yml');

    foreach ($routes as $route) {
        expect(Route::has($route))
            ->toBeTrue("Route '{$route}' from setup/navigation.yml does not exist");
    }
});

it('ensures all cms navigation routes exist', function (): void {
    $routes = extractNavigationRoutes('cms');

    expect($routes)->not->toBeEmpty('No routes found in cms/navigation.yml');

    foreach ($routes as $route) {
        expect(Route::has($route))
            ->toBeTrue("Route '{$route}' from cms/navigation.yml does not exist");
    }
});

/**
 * Extracts routes from navigation.yml block_menus.navigations entries.
 * Skips top-level hidden container routes which are not actual routes.
 */
function extractNavigationRoutes(string $app): array
{
    $yamlPath = base_path("app-configs/{$app}/navigation.yml");

    if (! file_exists($yamlPath)) {
        return [];
    }

    $content = file_get_contents($yamlPath);
    $navigation = Yaml::parse($content ?: '');

    if (! is_array($navigation)) {
        return [];
    }

    $routes = [];

    foreach ($navigation as $item) {
        if (! is_array($item)) {
            continue;
        }

        // Extract routes from block_menus.navigations (the actual clickable nav items)
        if (isset($item['block_menus']) && is_array($item['block_menus'])) {
            foreach ($item['block_menus'] as $blockMenu) {
                if (isset($blockMenu['navigations']) && is_array($blockMenu['navigations'])) {
                    foreach ($blockMenu['navigations'] as $navItem) {
                        if (isset($navItem['route']) && is_string($navItem['route'])) {
                            $routes[] = $navItem['route'];
                        }
                    }
                }
            }
        }

        // Also check sub_menu if present
        if (isset($item['sub_menu']) && is_array($item['sub_menu'])) {
            foreach ($item['sub_menu'] as $subItem) {
                if (isset($subItem['route']) && is_string($subItem['route'])) {
                    $routes[] = $subItem['route'];
                }
            }
        }
    }

    return array_unique($routes);
}
