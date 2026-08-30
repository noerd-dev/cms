<?php

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

    public function withHomepage(): static
    {
        return $this->state(fn(array $attributes): array => [
            'homepage_page_id' => Page::factory()->state(
                fn(): array => ['tenant_id' => $attributes['tenant_id']],
            ),
        ]);
    }
}
