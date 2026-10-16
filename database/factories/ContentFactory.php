<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Content;
use Noerd\Models\Tenant;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    protected $model = Content::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => 'cms.page',
            'name' => ['de' => $this->faker->sentence(3)],
        ];
    }
}
