<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Illuminate\Support\Facades\View;

/**
 * The frontend layouts a page can choose from: every `*.blade.php` in the
 * layout directory that does not start with an underscore. The directory is
 * `cms.layout_path` (relative to the project root) when set, otherwise the
 * `components/layouts` folder of the `website` view namespace — the installed
 * website module, wherever it lives. Falls back to a single `weblayout` entry
 * so the page editor always has a valid option.
 */
final class PageLayouts
{
    public const FALLBACK = 'weblayout';

    /**
     * @return array<string, string> layout name => layout name
     */
    public static function options(): array
    {
        $directory = self::directory();
        $options = [];

        if ($directory !== null && is_dir($directory)) {
            foreach (glob($directory . '/*.blade.php') ?: [] as $filePath) {
                $fileName = basename($filePath, '.blade.php');

                if (str_starts_with($fileName, '_')) {
                    continue;
                }

                $options[$fileName] = $fileName;
            }
        }

        return $options === [] ? [self::FALLBACK => self::FALLBACK] : $options;
    }

    /**
     * The directory the layouts are read from, or null when neither the
     * configuration nor a `website` view namespace names one.
     */
    public static function directory(): ?string
    {
        $configured = config('cms.layout_path');

        if (is_string($configured) && $configured !== '') {
            return base_path($configured);
        }

        foreach (View::getFinder()->getHints()['website'] ?? [] as $viewPath) {
            $directory = mb_rtrim((string) $viewPath, '/') . '/components/layouts';

            if (is_dir($directory)) {
                return $directory;
            }
        }

        return null;
    }

    public static function default(): string
    {
        return (string) array_key_first(self::options());
    }
}
