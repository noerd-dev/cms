<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

class CmsUpdateCommand extends CmsInstallCommand
{
    protected $signature = 'noerd:update-cms {--force : Overwrite existing files without asking}';

    protected $description = 'Update CMS YML configuration files';

    public function handle(): int
    {
        return $this->runModuleUpdate();
    }
}
