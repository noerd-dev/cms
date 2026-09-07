<?php

declare(strict_types=1);

namespace Noerd\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;

/**
 * @extends Factory<ElementPage>
 */
class ElementPageFactory extends Factory
{
    protected $model = ElementPage::class;

    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'element_key' => 'text_block_1_column',
            'sort' => 0,
            'data' => [],
        ];
    }
}
