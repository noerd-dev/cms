<?php

declare(strict_types=1);

namespace Noerd\Cms\Tests\Traits;

use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

trait CreatesCmsUser
{
    protected function createUserWithCmsAccess(): array
    {
        session(['currentApp' => 'cms']);

        $tenant = Tenant::factory()->create();

        $cmsApp = TenantApp::firstOrCreate(
            ['name' => 'CMS'],
            [
                'title' => 'CMS',
                'icon' => 'cms',
                'route' => 'cms.index',
                'is_active' => true,
            ],
        );

        $tenant->tenantApps()->attach($cmsApp->id);

        $user = User::factory()->create(['selected_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id);

        return ['user' => $user, 'tenant' => $tenant];
    }
}
