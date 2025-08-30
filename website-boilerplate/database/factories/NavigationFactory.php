<?php

namespace Noerd\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Website\Models\Navigation;

class NavigationFactory extends Factory
{
    protected $model = Navigation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null, // Should be set when creating
            'navigation_key' => 'main',
            'name' => json_encode(['de' => $this->faker->word(), 'en' => $this->faker->word()]),
            'page_id' => null,
            'link' => null,
            'new_tab' => false,
        ];
    }

    public function withPageId(int $pageId): static
    {
        return $this->state([
            'page_id' => $pageId,
        ]);
    }

    public function withLink(string $link): static
    {
        return $this->state([
            'link' => $link,
            'new_tab' => true,
        ]);
    }
}
