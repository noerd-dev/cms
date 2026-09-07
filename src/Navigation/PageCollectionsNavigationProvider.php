<?php

declare(strict_types=1);

namespace Noerd\Cms\Navigation;

class PageCollectionsNavigationProvider extends CollectionsNavigationProvider
{
    public function type(): string
    {
        return 'page-collections';
    }

    public function items(): array
    {
        return $this->getCollectionsByHasPage(hasPage: true);
    }
}
