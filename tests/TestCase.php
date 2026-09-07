<?php

declare(strict_types=1);

namespace Noerd\Cms\Tests;

use Illuminate\Support\Facades\File;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Providers\CmsServiceProvider;
use Noerd\Cms\Repositories\DatabaseCollectionDefinitionRepository;
use Noerd\Cms\Support\CmsLanguageCodes;
use Noerd\Communication\Providers\CommunicationServiceProvider;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Media\Providers\MediaServiceProvider;
use Noerd\Tests\TestCase as NoerdTestCase;

abstract class TestCase extends NoerdTestCase
{
    /**
     * The parent class provides the full standalone testbench setup: the noerd
     * providers, the noerd guard environment, the sqlite :memory: database
     * (NOERD_TESTBENCH_DB), the RefreshDatabaseState swap for mixed host/
     * testbench runs, and linking the noerd package itself into the skeleton.
     * This subclass adds the CMS package and its two hard dependencies on top.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->linkCmsModuleIntoSkeleton();

        // Process-global memo caches primed by an earlier test (or tenant).
        CmsLanguageCodes::clearCache();
        DatabaseCollectionDefinitionRepository::resetCache();
        FieldHelper::clearCache();
    }

    /**
     * StaticConfigHelper's module-source mapping is a process-global static that
     * is NOT flushed on app boot. These tests prime it against the testbench
     * skeleton's base_path — a host-application suite running later in the same
     * PHPUnit process would then resolve its module YAMLs against the skeleton
     * and silently lose them. Clear it so the next suite rebuilds its own view.
     */
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            StaticConfigHelper::clearModuleSourceCache();
            CmsLanguageCodes::clearCache();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            MediaServiceProvider::class,
            CommunicationServiceProvider::class,
            // Last: binds the PageElementService fallback key and the field types.
            CmsServiceProvider::class,
        ];
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

    /**
     * FieldHelper / HandlesPageElements glob base_path('app-modules/{star}/…') for
     * page elements and StaticConfigHelper discovers the module YAML sources the
     * same way. Under testbench base_path() is the skeleton application, so this
     * package is linked in once — mirroring what noerd:install-cms does in a
     * real project. No-op inside a host application, where the path exists.
     */
    private function linkCmsModuleIntoSkeleton(): void
    {
        $moduleTarget = base_path('app-modules/cms');

        if (file_exists($moduleTarget) || is_link($moduleTarget)) {
            return;
        }

        File::ensureDirectoryExists(base_path('app-modules'));
        @symlink(dirname(__DIR__), $moduleTarget);

        StaticConfigHelper::clearModuleSourceCache();
        StaticConfigHelper::flushRuntimeCaches();
    }
}
