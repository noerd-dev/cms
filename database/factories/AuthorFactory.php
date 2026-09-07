<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Author;
use Noerd\Models\Tenant;

class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'bio' => fake()->text(200),
            'is_active' => true,
        ];
    }
}
