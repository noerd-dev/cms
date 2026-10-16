<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;

/**
 * @extends Factory<ContentElement>
 */
class ContentElementFactory extends Factory
{
    protected $model = ContentElement::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'element_key' => 'text_block_1_column',
            'sort' => 0,
            'data' => [],
        ];
    }
}
