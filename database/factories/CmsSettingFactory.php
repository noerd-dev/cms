<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\Page;
use Noerd\Models\Tenant;

/**
 * @extends Factory<CmsSetting>
 */
class CmsSettingFactory extends Factory
{
    protected $model = CmsSetting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'homepage_page_id' => null,
        ];
    }

    /**
     * A homepage of the SAME tenant. Resolved after creating, because inside a
     * state closure the tenant_id is still the unresolved Tenant factory.
     */
    public function withHomepage(): static
    {
        return $this->afterCreating(function (CmsSetting $setting): void {
            $setting->update([
                'homepage_page_id' => Page::factory()->create(['tenant_id' => $setting->tenant_id])->id,
            ]);
        });
    }
}
