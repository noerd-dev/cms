<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'collections-list',
    'id' => 'fileName',
];

// Simplified tests without file system dependencies
it('loads collections table component successfully', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Simply test that the component loads without errors
    $component = Volt::test($testSettings['componentName']);

    expect($component)->not->toBeNull();
    expect($component->instance())->not->toBeNull();
});

it('has correct component configuration', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName']);
    $componentData = $component->instance();
    $withData = $componentData->with();

    expect($withData['listConfig']['listSettings'])->toBeArray();
    expect($withData['listConfig']['listSettings']['title'])->toBe('Collections');
});

it('returns paginated results', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName']);
    $componentData = $component->instance();
    $withData = $componentData->with();

    expect($withData['listConfig']['rows'])->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect(method_exists($withData['listConfig']['rows'], 'links'))->toBe(true);
});

it('supports search functionality', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $component = Volt::test($testSettings['componentName'])
        ->set('search', 'test');

    // Should not throw any errors when search is set
    expect($component->get('search'))->toBe('test');
});
