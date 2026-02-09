<?php

namespace Noerd\Cms\Commands;

class CmsUpdateCommand extends NoerdCmsInstallCommand
{
    protected $signature = 'noerd:update-cms {--force : Overwrite existing files without asking}';

    protected $description = 'Update CMS YML configuration files';

    public function handle(): int
    {
        return $this->runModuleUpdate();
    }
}
