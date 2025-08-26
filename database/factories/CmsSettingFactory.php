<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\Tenant;

/**
 * @extends Factory<CmsSetting>
 */
class CmsSettingFactory extends Factory
{
    protected $model = CmsSetting::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $page = Page::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'tenant_id' => $tenant->id,
            'homepage_page_id' => $page->id,
        ];
    }
}
