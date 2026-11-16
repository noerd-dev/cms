<?php

declare(strict_types=1);

namespace Noerd\Cms\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

/**
 * The CMS configuration moved from `config/noerd_cms.php` (`noerd_cms.*`) to
 * `config/cms.php` (`cms.*`) in 0.6. For this one release a project's earlier
 * file is still read: its values are merged over the module defaults while the
 * project has no `config/cms.php` of its own. The file is never touched;
 * `noerd:update-cms` tells the developer to rename it.
 *
 * @internal
 */
final class LegacyCmsConfig
{
    public const LEGACY_FILE = 'noerd_cms.php';

    public const FILE = 'cms.php';

    private static bool $noticeLogged = false;

    /**
     * Whether the project still carries the earlier file and not the new one.
     */
    public static function inUse(): bool
    {
        return is_file(config_path(self::LEGACY_FILE)) && ! is_file(config_path(self::FILE));
    }

    /**
     * Merge the earlier `noerd_cms` values over the `cms` defaults — once,
     * before the configuration is cached (a cached configuration already holds
     * the merged result).
     */
    public static function apply(Application $app): void
    {
        if ($app->configurationIsCached() || ! self::inUse()) {
            return;
        }

        $legacy = $app['config']->get('noerd_cms');

        if (! is_array($legacy) || $legacy === []) {
            return;
        }

        $app['config']->set('cms', array_replace((array) $app['config']->get('cms', []), $legacy));

        if (! self::$noticeLogged && ! $app->environment('production')) {
            self::$noticeLogged = true;
            Log::notice('config/cms.php is deprecated — rename it to config/cms.php (the keys stay, they are read as cms.*). It is read for the noerd/cms 0.6 line only.');
        }
    }
}
