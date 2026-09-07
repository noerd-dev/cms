<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Page;
use Noerd\Models\Tenant;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $deTitle = $this->faker->sentence(3);
        $enTitle = $this->faker->sentence(3);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => [
                'de' => $deTitle,
                'en' => $enTitle,
            ],
            'is_active' => true,
            'slug' => [
                'de' => '/' . str($deTitle)->slug()->toString(),
                'en' => '/en/' . str($enTitle)->slug()->toString(),
            ],
            'data' => [
                'name' => [
                    'de' => $deTitle,
                    'en' => $enTitle,
                ],
            ],
            'sort' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn(): array => ['is_active' => false]);
    }
}
