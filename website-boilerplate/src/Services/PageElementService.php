<?php

declare(strict_types=1);

namespace Noerd\Website\Services;

use Noerd\Cms\Services\PageElementService as CmsPageElementService;

/**
 * Website entry point for the page element service. All behaviour lives in the CMS
 * module (Noerd\Cms\Services\PageElementService); override here only for
 * website-specific customisations.
 */
class PageElementService extends CmsPageElementService {}
