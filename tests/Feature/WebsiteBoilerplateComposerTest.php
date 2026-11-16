<?php

declare(strict_types=1);

uses(Noerd\Cms\Tests\TestCase::class);

/*
 | The website boilerplate ships inside this package and requires it. Its
 | constraint must admit the CMS it is shipped with: the same minor as
 | composer.json's `version`, or the next minor (the boilerplate is raised to
 | the coming release before the version bump of that release lands). A
 | release that bumps the minor without the boilerplate fails here.
 */
it('requires a noerd/cms the boilerplate is shipped with', function (): void {
    $cms = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
    $boilerplate = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/website-boilerplate/composer.json'), true);

    $constraint = (string) ($boilerplate['require']['noerd/cms'] ?? '');

    expect($constraint)->toMatch('/^\^\d+\.\d+$/');

    [$constraintMajor, $constraintMinor] = array_map('intval', explode('.', mb_ltrim($constraint, '^')));
    [$major, $minor] = array_map('intval', array_slice(explode('.', mb_ltrim((string) $cms['version'], 'v')), 0, 2));

    expect($constraintMajor)->toBe($major)
        ->and($constraintMinor)->toBeGreaterThanOrEqual($minor)
        ->and($constraintMinor)->toBeLessThanOrEqual($minor + 1);
});

it('reaches the optional Turnstile package only at runtime', function (): void {
    // A static <x-turnstile…> tag is resolved when the view compiles and fails
    // without the package — the boilerplate does not require it.
    $views = Illuminate\Support\Facades\File::allFiles(dirname(__DIR__, 2) . '/website-boilerplate/resources/views');

    foreach ($views as $view) {
        expect((string) file_get_contents($view->getPathname()))
            ->not->toMatch('/<x-turnstile[\s.>]/')
            ->not->toContain('use RyanChandler');
    }
});
