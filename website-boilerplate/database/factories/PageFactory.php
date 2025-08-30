<?php

namespace Noerd\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Website\Models\Page;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null, // Should be set when creating
            'name' => json_encode(['de' => $this->faker->sentence(3), 'en' => $this->faker->sentence(3)]),
            'slug' => json_encode(['de' => '/' . $this->faker->slug(), 'en' => '/' . $this->faker->slug()]),
            'is_active' => true,
        ];
    }
}
