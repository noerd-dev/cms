<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Models\Tenant;

class CmsLanguageFactory extends Factory
{
    protected $model = CmsLanguage::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => $this->faker->unique()->languageCode(),
            'name' => $this->faker->word(),
        ];
    }

    public function english(): static
    {
        return $this->state(fn () => [
            'code' => 'en',
            'name' => 'English',
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
