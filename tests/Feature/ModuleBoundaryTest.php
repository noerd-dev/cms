<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);

it('declares every module dependency it uses', function (): void {
    // Allowed: the provider binds the website PageElementService purely by
    // container KEY (string, never a class load) as a fallback.
    assertModuleDependenciesDeclared(dirname(__DIR__, 2), ['noerd/website']);
});
