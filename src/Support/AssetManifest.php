<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use RuntimeException;

/**
 * Reads the Vite manifest of the prebuilt editor bundle (the content document
 * editor) that ships with the package.
 *
 * Every path is resolved relative to this file, so the same code serves the
 * bundle from a Composer installation (vendor/noerd/cms) and from a path
 * repository checkout (app-modules/cms) alike. Nothing is ever copied
 * into the host's public/ directory.
 */
final class AssetManifest
{
    public const string SCRIPT_ENTRY = 'resources/js/cms-editor.js';

    /**
     * Absolute path of the build directory holding manifest.json and the assets.
     */
    public static function buildDirectory(): string
    {
        return dirname(__DIR__, 2) . '/dist/build';
    }

    /**
     * The manifest as written by Vite, keyed by entry source path.
     *
     * @return array<string, array{file: string, css?: array<int, string>}>
     */
    public static function entries(): array
    {
        $manifestPath = self::buildDirectory() . '/manifest.json';

        if (! is_file($manifestPath)) {
            throw new RuntimeException('The CMS editor bundle is missing: ' . $manifestPath);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($manifest) ? $manifest : [];
    }

    /**
     * Every file the package may serve, as the basename used in the asset URL,
     * mapped to its absolute path on disk.
     *
     * @return array<string, string>
     */
    public static function servableFiles(): array
    {
        $files = [];

        foreach (self::entries() as $entry) {
            $candidates = array_merge([$entry['file'] ?? null], $entry['css'] ?? []);

            foreach (array_filter($candidates) as $relativePath) {
                $files[basename($relativePath)] = self::buildDirectory() . '/' . $relativePath;
            }
        }

        return $files;
    }

    /**
     * Basename of the bundled script for the package's single JS entry point.
     */
    public static function scriptFile(): string
    {
        $file = self::entries()[self::SCRIPT_ENTRY]['file'] ?? null;

        if ($file === null) {
            throw new RuntimeException('The CMS editor manifest has no entry for ' . self::SCRIPT_ENTRY);
        }

        return basename($file);
    }

    /**
     * Basenames of the stylesheets emitted for the script entry (the editor
     * stylesheet).
     *
     * @return array<int, string>
     */
    public static function styleFiles(): array
    {
        $styles = self::entries()[self::SCRIPT_ENTRY]['css'] ?? [];

        return array_map('basename', $styles);
    }

    /**
     * Absolute path of a servable file, or null when the manifest does not list it.
     */
    public static function path(string $file): ?string
    {
        return self::servableFiles()[$file] ?? null;
    }
}
