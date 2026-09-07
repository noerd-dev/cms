<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Models\Tenant;

class CollectionDefinitionFactory extends Factory
{
    protected $model = CollectionDefinition::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'tenant_id' => Tenant::factory(),
            'filename' => $slug,
            'key' => mb_strtoupper(str_replace('-', '_', $slug)),
            'title' => $this->faker->words(2, true),
            'title_list' => $this->faker->words(2, true),
            'description' => null,
            'has_page' => false,
            'fields' => [
                ['name' => 'headline', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6],
            ],
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
