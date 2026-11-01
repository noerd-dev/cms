<?php

declare(strict_types=1);

namespace Noerd\Cms\Tests;

use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Providers\CmsServiceProvider;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Communication\Providers\CommunicationServiceProvider;
use Noerd\Media\Providers\MediaServiceProvider;
use Noerd\Tests\ModuleTestCase;

/**
 * The standalone testbench setup of the core (providers, noerd guard, sqlite
 * :memory:, RefreshDatabaseState swap, the modules linked into the skeleton)
 * plus the CMS package and its two hard dependencies.
 */
abstract class TestCase extends ModuleTestCase
{
    protected array $moduleProviders = [
        MediaServiceProvider::class,
        CommunicationServiceProvider::class,
        // Last: binds the PageElementService fallback key and the field types.
        CmsServiceProvider::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Process-global memo caches primed by an earlier test (or tenant).
        CmsLanguageCodes::clearCache();
        DatabaseCollectionDefinitionRepository::resetCache();
        FieldHelper::clearCache();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            CmsLanguageCodes::clearCache();
        }
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The "media" disk is host configuration (config/filesystems.php of a
        // real project) — mirror it so MediaUploadService and the media picker
        // resolve a disk and a public URL under testbench.
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('filesystems.disks.media', [
            'driver' => 'local',
            'root' => storage_path('app/public/media'),
            'url' => 'http://localhost/storage/media',
            'visibility' => 'public',
            'throw' => false,
        ]);
        $app['config']->set('media.disk', 'media');
        $app['config']->set('media.private', false);

        // Never inherit a host .env: the tests set their own paths and URLs.
        $app['config']->set('noerd_cms.page_elements_path', null);
        $app['config']->set('noerd_cms.website_url', '');
    }
}
