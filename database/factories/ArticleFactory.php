<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $deTitle = fake()->words(3, true);
        $enTitle = fake()->words(3, true);

        return [
            'tenant_id' => 1,
            'author_id' => null,
            'title' => [
                'de' => $deTitle,
                'en' => $enTitle,
            ],
            'slug' => [
                'de' => '/' . str($deTitle)->slug()->toString(),
                'en' => '/en/' . str($enTitle)->slug()->toString(),
            ],
            'body' => fake()->text(500),
            'featured_image' => null,
            'publication_date' => fake()->date(),
            'is_active' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'publication_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'publication_date' => fake()->dateTimeBetween('+1 day', '+1 year'),
        ]);
    }
}
