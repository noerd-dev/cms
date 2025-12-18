<?php

declare(strict_types=1);

namespace Noerd\Cms\Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

trait CreatesCmsUser
{
    use RefreshDatabase;
    protected function createUserWithCmsAccess(): array
    {
        $tenant = Tenant::factory()->create();

        $cmsApp = TenantApp::firstOrCreate(
            ['name' => 'CMS'],
            [
                'title' => 'CMS',
                'icon' => 'cms::icons.app',
                'route' => 'cms.index',
                'is_active' => true,
            ],
        );

        $tenant->tenantApps()->attach($cmsApp->id);

        $user = User::factory()->create([
            'selected_tenant_id' => $tenant->id,
            'selected_app' => 'cms',
        ]);
        $user->tenants()->attach($tenant->id);

        return ['user' => $user, 'tenant' => $tenant];
    }
}
