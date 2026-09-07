<?php

declare(strict_types=1);

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
            'name' => ['de' => $this->faker->sentence(3), 'en' => $this->faker->sentence(3)],
            'slug' => ['de' => '/' . $this->faker->slug(), 'en' => '/' . $this->faker->slug()],
            'is_active' => true,
        ];
    }
}
