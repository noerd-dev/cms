<?php

namespace Noerd\Cms\Commands;

class CmsUpdateCommand extends CmsInstallCommand
{
    protected $signature = 'noerd:update-cms {--force : Overwrite existing files without asking}';

    protected $description = 'Update CMS YML configuration files';

    public function handle(): int
    {
        $result = $this->runModuleUpdate();

        if ($result === 0) {
            // Idempotent post-install step: tenants created since the install
            // get their starter homepage too.
            $this->seedDefaultHomepage();
        }

        return $result;
    }
}
