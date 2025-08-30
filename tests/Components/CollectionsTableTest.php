<?php

use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

$testSettings = [
    'componentName' => 'collections-table',
    'id' => 'fileName',
];

beforeEach(function (): void {
    // Ensure collections directory exists
    $collectionsPath = base_path('content/collections');
    if (!File::exists($collectionsPath)) {
        File::makeDirectory($collectionsPath, 0755, true);
    }
});

afterEach(function (): void {
    // Clean up test collection files
    $collectionsPath = base_path('content/collections');
    $testFiles = File::glob($collectionsPath . '/test-*.yml');
    foreach ($testFiles as $file) {
        File::delete($file);
    }
});

it('displays collections table correctly', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    // Create some test collection files
    $collectionsPath = base_path('content/collections');
    File::put($collectionsPath . '/test-collection-1.yml', "title: Test Collection 1\nfields: []");
    File::put($collectionsPath . '/test-collection-2.yml', "title: Test Collection 2\nfields: []");
    File::put($collectionsPath . '/test-collection-3.yml', "title: Test Collection 3\nfields: []");
    
    $component = Volt::test($testSettings['componentName']);
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    expect($withData['rows'])->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($withData['tableConfig'])->toBeArray();
    expect($withData['tableConfig']['title'])->toBe('Collections');
    expect($withData['rows']->count())->toBeGreaterThanOrEqual(3);
});

it('handles pagination correctly with many collection files', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    // Create more collection files than the pagination limit (50)
    $collectionsPath = base_path('content/collections');
    for ($i = 1; $i <= 60; $i++) {
        File::put($collectionsPath . "/test-collection-{$i}.yml", "title: Test Collection {$i}\nfields: []");
    }
    
    $component = Volt::test($testSettings['componentName']);
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    // Should have pagination
    expect($withData['rows'])->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($withData['rows']->hasPages())->toBe(true);
    expect($withData['rows']->lastPage())->toBe(2);
    expect($withData['rows']->count())->toBe(50); // First page should have 50 items
    expect($withData['rows']->total())->toBeGreaterThanOrEqual(60); // Total should be at least 60
});

it('has links method available for pagination', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    // Create enough files to trigger pagination
    $collectionsPath = base_path('content/collections');
    for ($i = 1; $i <= 55; $i++) {
        File::put($collectionsPath . "/test-collection-{$i}.yml", "title: Test Collection {$i}\nfields: []");
    }
    
    $component = Volt::test($testSettings['componentName']);
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    // Verify the links method exists and works
    expect(method_exists($withData['rows'], 'links'))->toBe(true);
    expect($withData['rows']->links())->not->toBeNull();
});

it('filters collections by search term', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    // Create collection files with specific names
    $collectionsPath = base_path('content/collections');
    File::put($collectionsPath . '/test-products.yml', "title: Products\nfields: []");
    File::put($collectionsPath . '/test-services.yml', "title: Services\nfields: []");
    File::put($collectionsPath . '/test-customers.yml', "title: Customers\nfields: []");
    File::put($collectionsPath . '/test-orders.yml', "title: Orders\nfields: []");
    
    // Test search by filename
    $component = Volt::test($testSettings['componentName'])
        ->set('search', 'products');
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    expect($withData['rows']->count())->toBe(1);
    expect($withData['rows']->first()['name'])->toBe('test-products');
    
    // Test search by partial name
    $component = Volt::test($testSettings['componentName'])
        ->set('search', 'test-');
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    expect($withData['rows']->count())->toBe(4);
});

it('sorts collections alphabetically by name', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    // Create collection files with names that should be sorted
    $collectionsPath = base_path('content/collections');
    File::put($collectionsPath . '/test-zebra.yml', "title: Zebra\nfields: []");
    File::put($collectionsPath . '/test-alpha.yml', "title: Alpha\nfields: []");
    File::put($collectionsPath . '/test-beta.yml', "title: Beta\nfields: []");
    
    $component = Volt::test($testSettings['componentName']);
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    $names = $withData['rows']->pluck('name')->toArray();
    
    // Find our test files in the sorted array
    $testAlphaIndex = array_search('test-alpha', $names);
    $testBetaIndex = array_search('test-beta', $names);
    $testZebraIndex = array_search('test-zebra', $names);
    
    // Should be sorted alphabetically (alpha < beta < zebra)
    expect($testAlphaIndex)->toBeLessThan($testBetaIndex);
    expect($testBetaIndex)->toBeLessThan($testZebraIndex);
});

it('handles table action correctly', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    $collectionsPath = base_path('content/collections');
    File::put($collectionsPath . '/test-collection.yml', "title: Test Collection\nfields: []");
    
    $component = Volt::test($testSettings['componentName']);
    
    // Test table action dispatches correct event
    $component->call('tableAction', 'test-collection.yml')
        ->assertDispatched('noerdModal');
});

it('handles file deletion correctly', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    $collectionsPath = base_path('content/collections');
    $testFile = $collectionsPath . '/test-delete-me.yml';
    File::put($testFile, "title: Delete Me\nfields: []");
    
    expect(File::exists($testFile))->toBe(true);
    
    $component = Volt::test($testSettings['componentName']);
    
    // Test file deletion
    $component->call('deleteFile', 'test-delete-me.yml')
        ->assertDispatched('noerd-notification');
    
    expect(File::exists($testFile))->toBe(false);
});

it('displays file information correctly', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    $collectionsPath = base_path('content/collections');
    $testContent = "title: Test Collection\nfields:\n  - name: test_field\n    type: text";
    File::put($collectionsPath . '/test-info.yml', $testContent);
    
    $component = Volt::test($testSettings['componentName']);
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    $testFile = $withData['rows']->firstWhere('name', 'test-info');
    
    expect($testFile)->not->toBeNull();
    expect($testFile['file_name'])->toBe('test-info.yml');
    expect($testFile['last_modified'])->toBeString();
    expect($testFile['size'])->toBeString();
    expect($testFile['size'])->toContain('B'); // Should contain bytes indicator
});

it('handles empty collections directory gracefully', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    // Ensure directory exists but is empty
    $collectionsPath = base_path('content/collections');
    if (File::exists($collectionsPath)) {
        $files = File::files($collectionsPath);
        foreach ($files as $file) {
            if (str_contains($file->getFilename(), 'test-')) {
                File::delete($file->getPathname());
            }
        }
    }
    
    $component = Volt::test($testSettings['componentName']);
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    expect($withData['rows'])->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($withData['rows']->count())->toBeGreaterThanOrEqual(0);
    expect(method_exists($withData['rows'], 'links'))->toBe(true);
});

it('handles non-yml files correctly', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    $collectionsPath = base_path('content/collections');
    
    // Create yml and non-yml files
    File::put($collectionsPath . '/test-valid.yml', "title: Valid Collection\nfields: []");
    File::put($collectionsPath . '/test-invalid.txt', "This is not a yml file");
    File::put($collectionsPath . '/test-invalid.json', '{"title": "Not a yml file"}');
    
    $component = Volt::test($testSettings['componentName']);
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    // Should only include .yml files
    $fileNames = $withData['rows']->pluck('file_name')->toArray();
    
    expect(in_array('test-valid.yml', $fileNames))->toBe(true);
    expect(in_array('test-invalid.txt', $fileNames))->toBe(false);
    expect(in_array('test-invalid.json', $fileNames))->toBe(false);
});

it('pagination works correctly with search', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    
    $this->actingAs($user);
    
    $collectionsPath = base_path('content/collections');
    
    // Create many files with similar names
    for ($i = 1; $i <= 60; $i++) {
        File::put($collectionsPath . "/test-search-{$i}.yml", "title: Search Test {$i}\nfields: []");
    }
    
    // Create some files with different names
    for ($i = 1; $i <= 5; $i++) {
        File::put($collectionsPath . "/test-different-{$i}.yml", "title: Different {$i}\nfields: []");
    }
    
    // Search for "search" - should find 60 files
    $component = Volt::test($testSettings['componentName'])
        ->set('search', 'search');
    
    $componentData = $component->instance();
    $withData = $componentData->with();
    
    expect($withData['rows']->hasPages())->toBe(true);
    expect($withData['rows']->total())->toBe(60);
    expect($withData['rows']->count())->toBe(50); // First page
    expect($withData['rows']->lastPage())->toBe(2);
});
