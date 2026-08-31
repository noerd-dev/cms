<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Global test helpers
|--------------------------------------------------------------------------
|
| These tests bind Tests\TestCase (the host application's), not
| Noerd\Tests\TestCase, so the noerd helpers (validDetailPayload,
| requiredLayoutFields, registerTestLivewireRoute, ...) are not loaded through
| that class. They are deliberately absent from the production composer
| autoload, so load them explicitly. HelperLoader resolves the file through the
| autoloader and therefore works whether noerd is installed as a composer
| package or as a submodule.
|
*/

\Noerd\Tests\HelperLoader::load();

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        $tenant = Tenant::factory()->create();

        // Check if CMS app already exists, otherwise create it
        $cmsApp = TenantApp::firstOrCreate(
            ['name' => 'CMS'],
            [
                'title' => 'CMS',
                'icon' => 'cms::icons.app',
                'route' => 'cms.dashboard',
                'is_active' => true,
            ],
        );

        $tenant->tenantApps()->attach($cmsApp->id);

        // Ensure default English language exists for this tenant
        CmsLanguage::ensureDefaultLanguageForTenant($tenant->id);

        $this->user = NoerdUser::factory()->create();
        $this->user->tenants()->attach($tenant->id);
        TenantHelper::setSelectedTenantId($tenant->id);
        $this->tenant = $tenant;
    })
    ->in(__DIR__);
