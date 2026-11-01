<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands\Concerns;

use Symfony\Component\Yaml\Yaml;

/**
 * The "To Website" quick-menu button of the CMS. The component is namespaced
 * (`cms::quick-menu.website-link`) since the CMS no longer registers its
 * components under their bare names; an installation still listing the former
 * bare name has that entry renamed first, so the quick menu never tries to
 * mount a component that no longer resolves.
 *
 * Requires WritesHostAppConfigs (ensureQuickMenuButton()).
 */
trait EnsuresWebsiteLinkButton
{
    public const string WEBSITE_LINK_BUTTON = 'cms::quick-menu.website-link';

    private const string LEGACY_WEBSITE_LINK_BUTTON = 'quick-menu.website-link';

    protected function ensureWebsiteLinkButton(): void
    {
        $this->renameLegacyWebsiteLinkButton();

        $this->ensureQuickMenuButton(['apps' => ['CMS'], 'component' => self::WEBSITE_LINK_BUTTON]);
    }

    private function renameLegacyWebsiteLinkButton(): void
    {
        $configPath = base_path('app-configs/quick-menu.yml');

        if (! file_exists($configPath)) {
            return;
        }

        $config = Yaml::parse(file_get_contents($configPath) ?: '') ?? [];
        $buttons = $config['buttons'] ?? [];
        $hasCurrent = in_array(self::WEBSITE_LINK_BUTTON, array_column($buttons, 'component'), true);
        $changed = false;

        foreach ($buttons as $i => $button) {
            if (($button['component'] ?? null) !== self::LEGACY_WEBSITE_LINK_BUTTON) {
                continue;
            }

            if ($hasCurrent) {
                unset($buttons[$i]);
            } else {
                $buttons[$i]['component'] = self::WEBSITE_LINK_BUTTON;
                $hasCurrent = true;
            }

            $changed = true;
        }

        if ($changed) {
            $config['buttons'] = array_values($buttons);
            file_put_contents($configPath, Yaml::dump($config, 10, 2));
        }
    }
}
