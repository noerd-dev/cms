<?php

namespace Noerd\Cms\Navigation;

use Noerd\Cms\Contracts\CollectionDefinitionRepositoryContract;
use Noerd\Cms\Support\CollectionDefinitionData;
use Noerd\Contracts\DynamicNavigationProviderContract;

class CollectionsNavigationProvider implements DynamicNavigationProviderContract
{
    public function __construct(
        protected readonly CollectionDefinitionRepositoryContract $repository,
    ) {}

    public function type(): string
    {
        return 'collections';
    }

    public function items(): array
    {
        return $this->getCollectionsByHasPage(hasPage: false);
    }

    /**
     * @return array<int, array{title: string, link: string, icon: string}>
     */
    protected function getCollectionsByHasPage(bool $hasPage): array
    {
        return $this->repository->all()
            ->filter(fn (CollectionDefinitionData $d) => $d->hasPage === $hasPage)
            ->map(fn (CollectionDefinitionData $d) => [
                'title' => $d->titleList,
                'link' => "/cms/collections?key={$d->filename}",
                'icon' => 'icons.list-bullet',
            ])
            ->values()
            ->toArray();
    }
}
