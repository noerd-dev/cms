<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\GlobalParameter;

class GlobalParameterFactory extends Factory
{
    protected $model = GlobalParameter::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'key' => $this->faker->unique()->word,
            'value' => json_encode($this->faker->sentence),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function withTenantId($tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function withKeyValue($key, $value): static
    {
        return $this->state(fn(array $attributes) => [
            'key' => $key,
            'value' => json_encode($value),
        ]);
    }
}
