<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Collection;

class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'collection_key' => mb_strtoupper($this->faker->word),
            'data' => json_encode([
                'title' => [
                    'de' => $this->faker->sentence(3),
                    'en' => $this->faker->sentence(3),
                ],
                'description' => [
                    'de' => $this->faker->paragraph,
                    'en' => $this->faker->paragraph,
                ],
            ]),
            'sort' => $this->faker->numberBetween(1, 100),
            'page_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function withPageId($pageId): static
    {
        return $this->state(fn(array $attributes) => [
            'page_id' => $pageId,
        ]);
    }
}
