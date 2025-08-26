<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionRow;

class CollectionRowFactory extends Factory
{
    protected $model = CollectionRow::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'collection_id' => Collection::factory(),
            'data' => json_encode([
                'name' => [
                    'de' => $this->faker->words(3, true),
                    'en' => $this->faker->words(3, true),
                ],
            ]),
            'sort' => $this->faker->numberBetween(1, 100),
        ];
    }
}
