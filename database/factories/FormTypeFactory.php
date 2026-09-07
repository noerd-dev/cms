<?php

declare(strict_types=1);

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
            // Resolves to nothing on purpose: a form type without a YAML file
            // validates no fields. Point it at a fixture with withYaml().
            'yml_path' => 'forms/' . $this->faker->slug(2) . '.yml',
        ];
    }

    /**
     * Bind the form type to a YAML definition (absolute path or relative to base_path()).
     */
    public function withYaml(string $ymlPath): static
    {
        return $this->state(fn(): array => ['yml_path' => $ymlPath]);
    }
}
