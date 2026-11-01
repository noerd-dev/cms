<?php

declare(strict_types=1);

namespace Noerd\Cms\Commands;

class CmsUpdateCommand extends CmsInstallCommand
{
    protected $signature = 'noerd:update-cms';

    protected $description = 'Update CMS YML configuration files';
}
