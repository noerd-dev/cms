<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Models\Redirect;
use Noerd\Models\Tenant;

class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'source_path' => '/' . $this->faker->unique()->slug(2),
            'target_page_id' => Page::factory(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn(): array => ['is_active' => false]);
    }
}
