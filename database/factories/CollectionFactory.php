<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Collection;
use Noerd\Models\Tenant;

class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'collection_key' => mb_strtoupper($this->faker->unique()->lexify('COLLECTION_??????')),
            'name' => $this->faker->words(2, true),
        ];
    }
}
