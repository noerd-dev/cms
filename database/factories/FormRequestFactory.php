<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\FormRequest;
use Noerd\Models\Tenant;

class FormRequestFactory extends Factory
{
    protected $model = FormRequest::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'form' => $this->faker->word(),
        ];
    }
}
