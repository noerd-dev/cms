<?php

namespace Noerd\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Website\Models\FormRequest;

class FormRequestFactory extends Factory
{
    protected $model = FormRequest::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null, // Should be set when creating
            'form' => 'contact',
            'data' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
                'phone' => $this->faker->phoneNumber(),
                'message' => $this->faker->paragraph(),
            ],
        ];
    }
}
