<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

uses(Noerd\Cms\Tests\TestCase::class);

it('renders the shipped Boost guideline as plain markdown', function (): void {
    $rendered = Blade::render(file_get_contents(dirname(__DIR__, 2) . '/resources/boost/guidelines/core.blade.php'));

    expect($rendered)
        ->toContain('## CMS Module')
        ->not->toContain('@verbatim')
        ->not->toContain('@endverbatim');
});
