<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('registers the install and update commands', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('noerd:update-cms');
    expect($commands)->toHaveKey('noerd:install-cms');
});

it('publishes the module configs through the update command', function (): void {
    assertModuleUpdateCommandPublishesConfigs('noerd:update-cms', dirname(__DIR__, 2), 'cms');
});
