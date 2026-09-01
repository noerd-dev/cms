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
        // Every tenant already owns `en` through ensureDefaultLanguageForTenant(),
        // and cms_languages is unique per (tenant_id, code). Rolling that one code
        // therefore collides, which made any test creating a language for an
        // existing tenant fail on the dice rather than on its own behaviour.
        do {
            $code = $this->faker->unique()->languageCode();
        } while ($code === 'en');

        return [
            'tenant_id' => Tenant::factory(),
            'code' => $code,
            'name' => $this->faker->word(),
        ];
    }

    public function english(): static
    {
        return $this->state(fn() => [
            'code' => 'en',
            'name' => 'English',
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
