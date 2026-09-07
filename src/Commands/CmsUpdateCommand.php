<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

class CmsUpdateCommand extends CmsInstallCommand
{
    protected $signature = 'noerd:update-cms {--force : Overwrite existing files without asking}';

    protected $description = 'Update CMS YML configuration files';

    public function handle(): int
    {
        $result = $this->runModuleUpdate();

        if ($result === 0) {
            // A project installed before a config key existed gets the file;
            // an existing file is never touched.
            $this->publishConfigIfMissing();

            // Idempotent post-install step: tenants created since the install
            // get their starter homepage too.
            $this->seedDefaultHomepage();

            // Idempotent: re-ensures the "To Website" button and migrates a
            // legacy `policy:` entry that would otherwise hide it.
            $this->installQuickMenuConfig();
        }

        return $result;
    }
}
