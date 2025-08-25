<?php

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Page;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $deTitle = $this->faker->sentence(3);
        $enTitle = $this->faker->sentence(3);

        return [
            'tenant_id' => 1,
            'name' => '{"de":"' . $deTitle . '","en":"' . $enTitle . '"}',
            'slug' => '{"de":"/' . $this->generateSlug($deTitle) . '","en":"/' . $this->generateSlug($enTitle) . '"}',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function generateSlug(string $name): string
    {
        // Replace umlauts and special characters BEFORE lowercasing
        $slug = str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $name);

        // Convert to lowercase
        $slug = mb_strtolower($slug);

        // Remove all non-alphanumeric characters and spaces, replace with hyphens
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

        // Replace multiple spaces/hyphens with single hyphen
        $slug = preg_replace('/[\s-]+/', '-', $slug);

        // Trim hyphens from beginning and end
        $slug = mb_trim($slug, '-');

        return $slug;
    }
}
