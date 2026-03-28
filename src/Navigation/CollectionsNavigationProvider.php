<?php

namespace Noerd\Cms\Navigation;

use Exception;
use Noerd\Contracts\DynamicNavigationProviderContract;
use Symfony\Component\Yaml\Yaml;

class CollectionsNavigationProvider implements DynamicNavigationProviderContract
{
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
        $collectionsPath = base_path('app-configs/cms/collections');

        if (! is_dir($collectionsPath)) {
            return [];
        }

        $collectionFiles = glob($collectionsPath . '/*.yml');
        $dynamicNavigations = [];

        foreach ($collectionFiles as $file) {
            $collectionKey = basename($file, '.yml');

            try {
                $content = file_get_contents($file);
                $collectionData = Yaml::parse($content ?: '');

                if ($collectionData && isset($collectionData['titleList'])) {
                    $collectionHasPage = $collectionData['hasPage'] ?? false;

                    if ($collectionHasPage === $hasPage) {
                        $dynamicNavigations[] = [
                            'title' => $collectionData['titleList'],
                            'link' => "/cms/collections?key={$collectionKey}",
                            'icon' => 'icons.list-bullet',
                        ];
                    }
                }
            } catch (Exception) {
                continue;
            }
        }

        return $dynamicNavigations;
    }
}
