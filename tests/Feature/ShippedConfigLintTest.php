<?php

declare(strict_types=1);

use Noerd\Support\ConfigLinter;

uses(Noerd\Cms\Tests\TestCase::class);

/*
 | Architecture guard: the YAML this module ships (app-configs/) passes the
 | core's linter without an ERROR — field types, modal targets, picklists and
 | the YAML vocabulary of the installed noerd. It asserts no content.
 */
it('ships app-configs without lint errors', function (): void {
    $issues = app(ConfigLinter::class)->lint([dirname(__DIR__, 2) . '/app-configs']);

    $errors = array_values(array_map(
        fn(array $issue): string => basename($issue['file']) . ' ' . $issue['location'] . ': ' . $issue['message'],
        array_filter($issues, fn(array $issue): bool => $issue['severity'] === ConfigLinter::ERROR),
    ));

    expect($errors)->toBe([]);
});

it('prints the warnings for review', function (): void {
    $issues = app(ConfigLinter::class)->lint([dirname(__DIR__, 2) . '/app-configs']);

    foreach ($issues as $issue) {
        if ($issue['severity'] === ConfigLinter::WARNING) {
            fwrite(STDERR, 'LINT-WARNING ' . basename($issue['file']) . ' ' . $issue['location'] . ': ' . $issue['message'] . PHP_EOL);
        }
    }

    expect(true)->toBeTrue();
});
