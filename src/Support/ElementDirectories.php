<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

/**
 * The folders page elements (`{file}.blade.php` + `{file}.yml`) are discovered
 * in — the one place FieldHelper and HandlesPageElements read them from.
 *
 * A module's elements live in `resources/views/components/elements/` of the
 * module, wherever Composer installed it: a git submodule below `app-modules/`
 * or a package below `vendor/` (the CMS's own running text `rich_text` is such
 * a package element). Those folders are found through the Livewire namespace
 * every module registers for its components folder; `app-modules/*` is scanned
 * as well for a module that registers none.
 */
final class ElementDirectories
{
    private const string ELEMENTS = 'elements';

    /**
     * Every existing elements folder, LOWEST precedence first: the modules,
     * then the project's own `resources/views/components/elements/`, so a
     * project element shadows a module element of the same name.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $candidates = array_map(
            fn(string $componentsDir): string => $componentsDir . '/' . self::ELEMENTS,
            array_keys(self::livewireNamespacesByPath()),
        );

        $candidates = array_merge(
            $candidates,
            glob(base_path('app-modules/*/resources/views/components/' . self::ELEMENTS), GLOB_ONLYDIR) ?: [],
            [base_path('resources/views/components/' . self::ELEMENTS)],
        );

        // A module linked into vendor/ (path repository) is the same folder as
        // its app-modules/ checkout — keep its LAST position, so the project
        // folder, which comes last, always keeps the highest precedence.
        $directories = [];
        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);

            if ($resolved === false || ! is_dir($resolved)) {
                continue;
            }

            unset($directories[$resolved]);
            $directories[$resolved] = $resolved;
        }

        return array_values($directories);
    }

    /**
     * Every element file with the given file pattern, LOWEST precedence first.
     *
     * @return array<int, string>
     */
    public static function files(string $pattern): array
    {
        $files = [];

        foreach (self::all() as $directory) {
            array_push($files, ...(glob($directory . '/' . $pattern) ?: []));
        }

        return $files;
    }

    /**
     * The registered Livewire view namespaces keyed by their resolved
     * components folder (`/…/cms/resources/views/components` => `cms`).
     *
     * @return array<string, string>
     */
    public static function livewireNamespacesByPath(): array
    {
        if (! app()->bound('livewire.finder')) {
            return [];
        }

        $viewNamespaces = (fn(): array => $this->viewNamespaces ?? [])->call(app('livewire.finder'));

        $byPath = [];
        foreach ($viewNamespaces as $namespace => $viewPath) {
            if (is_string($viewPath)) {
                $byPath[realpath($viewPath) ?: mb_rtrim($viewPath, '/')] = (string) $namespace;
            }
        }

        return $byPath;
    }
}
