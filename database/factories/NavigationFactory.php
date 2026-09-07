<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Navigation;
use Noerd\Models\Tenant;

class NavigationFactory extends Factory
{
    protected $model = Navigation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'navigation_key' => $this->faker->unique()->slug(2),
        ];
    }
}
