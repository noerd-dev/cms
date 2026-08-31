<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Author;

class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'name' => fake()->name(),
            'bio' => fake()->text(200),
            'is_active' => true,
        ];
    }
}
