<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Page;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'name' => '{"de":"' . $this->faker->sentence(3) . '","en":"' . $this->faker->sentence(3) . '"}',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
