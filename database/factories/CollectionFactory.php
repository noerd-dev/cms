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
            'collection_key' => mb_strtoupper($this->faker->unique()->lexify('COLLECTION_??????')),
            'name' => $this->faker->words(2, true),
        ];
    }
}
