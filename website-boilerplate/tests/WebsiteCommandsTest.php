<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\TestCase::class, RefreshDatabase::class);

it('registers the update command', function (): void {
    expect(Artisan::all())->toHaveKey('noerd:update-website');
});

it('runs the update command successfully', function (): void {
    // The website module has no publishable configuration — the command exists
    // so noerd:update-all covers the module and must simply exit successfully.
    expect(Artisan::call('noerd:update-website'))->toBe(0);
    expect(Artisan::output())->toContain('Website module');
});
