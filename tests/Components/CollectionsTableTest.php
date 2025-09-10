<?php

use Livewire\Volt\Volt;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

$testSettings = [
    'componentName' => 'collections-table',
    'id' => 'fileName',
];

// Simplified tests without file system dependencies
it('loads collections table component successfully', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    // Simply test that the component loads without errors
    $component = Volt::test($testSettings['componentName']);

    expect($component)->not->toBeNull();
    expect($component->instance())->not->toBeNull();
});

it('has correct component configuration', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName']);
    $componentData = $component->instance();
    $withData = $componentData->with();

    expect($withData['tableConfig'])->toBeArray();
    expect($withData['tableConfig']['title'])->toBe('Collections');
});

it('returns paginated results', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName']);
    $componentData = $component->instance();
    $withData = $componentData->with();

    expect($withData['rows'])->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect(method_exists($withData['rows'], 'links'))->toBe(true);
});

it('supports search functionality', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName'])
        ->set('search', 'test');

    // Should not throw any errors when search is set
    expect($component->get('search'))->toBe('test');
});
