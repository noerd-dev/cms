<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

/**
 * The frontend layouts a page can choose from: every `*.blade.php` in the
 * configured layout directory (`noerd_cms.layout_path`) that does not start
 * with an underscore. Falls back to a single `weblayout` entry so the page
 * editor always has a valid option.
 */
final class PageLayouts
{
    public const FALLBACK = 'weblayout';

    /**
     * @return array<string, string> layout name => layout name
     */
    public static function options(): array
    {
        $directory = base_path((string) config('noerd_cms.layout_path', 'app-modules/website/resources/views/components/layouts'));
        $options = [];

        if (is_dir($directory)) {
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

    public static function default(): string
    {
        return (string) array_key_first(self::options());
    }
}
