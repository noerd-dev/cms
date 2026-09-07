<?php

declare(strict_types=1);

use Noerd\Cms\Models\Redirect;

uses(Noerd\Cms\Tests\TestCase::class);

/*
 | The website boilerplate carries its own copy of Redirect::normalizePath()
 | (the frontend matches incoming paths with it). Both copies must agree.
 */
it('normalizes paths identically in the CMS and the website boilerplate', function (string $input, string $expected): void {
    $boilerplate = 'Noerd\Website\Models\Redirect';

    if (! class_exists($boilerplate, false)) {
        require_once dirname(__DIR__, 2) . '/website-boilerplate/src/Models/Redirect.php';
    }

    expect(Redirect::normalizePath($input))->toBe($expected)
        ->and($boilerplate::normalizePath($input))->toBe($expected);
})->with([
    'trailing slash' => ['Agentur/', '/agentur'],
    'query and fragment' => ['/A?x=1#top', '/a'],
    'empty' => ['', '/'],
    'surrounding slashes and spaces' => ['  //ueber/uns//  ', '/ueber/uns'],
    'unicode' => ['/Über', '/über'],
]);
