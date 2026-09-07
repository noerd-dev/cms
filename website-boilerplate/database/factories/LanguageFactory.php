<?php

declare(strict_types=1);

namespace Noerd\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Website\Models\Language;

class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null, // Should be set when creating
            'code' => $this->faker->unique()->languageCode(),
            'name' => $this->faker->word(),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 1,
        ];
    }

    public function default(): static
    {
        return $this->state([
            'is_default' => true,
        ]);
    }
}
