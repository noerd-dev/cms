<?php

namespace Noerd\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Website\Models\Tenant;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'hash' => $this->faker->unique()->md5(),
        ];
    }
}
