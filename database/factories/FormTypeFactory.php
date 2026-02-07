<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\FormType;
use Noerd\Models\Tenant;

class FormTypeFactory extends Factory
{
    protected $model = FormType::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => $this->faker->unique()->slug(2),
            'title' => $this->faker->sentence(3),
            'yml_path' => 'forms/' . $this->faker->slug(2) . '.yml',
        ];
    }
}
