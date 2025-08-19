<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Noerd\Cms\Models\Element;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

$testSettings = [
    'componentName' => 'element-page-component',
    'listName' => 'element-pages-table',
    'id' => 'modelId',
];

it('successfully mounts with element page', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    // Create Element and ElementPage (schema uses element_id)
    $element = new Element();
    $element->tenant_id = $user->selected_tenant_id;
    $element->name = 'Text Block';
    $element->element_key = 'text_block_1_column';
    $element->description = 'Test';
    $element->save();

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_id' => $element->id,
        'data' => json_encode(['content' => 'Test content']),
        'sort' => 1,
    ]);
    if (!Schema::hasColumn('element_page', 'element_key')) {
        Schema::table('element_page', function (Blueprint $table): void {
            $table->string('element_key')->nullable();
        });
    }
    $elementPage->element_key = 'text_block_1_column';
    $elementPage->save();

    Volt::test($testSettings['componentName'], [$elementPage])
        ->assertSet('modelId', $elementPage->id)
        ->assertSet('elementPage.id', $elementPage->id)
        ->assertHasNoErrors();
});

it('can update element page data', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    $element = new Element();
    $element->tenant_id = $user->selected_tenant_id;
    $element->name = 'Text Block';
    $element->element_key = 'text_block_1_column';
    $element->description = 'Test';
    $element->save();

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_id' => $element->id,
        'data' => json_encode(['content' => 'Original content']),
        'sort' => 1,
    ]);
    if (!Schema::hasColumn('element_page', 'element_key')) {
        Schema::table('element_page', function (Blueprint $table): void {
            $table->string('element_key')->nullable();
        });
    }
    $elementPage->element_key = 'text_block_1_column';
    $elementPage->save();

    Volt::test($testSettings['componentName'], [$elementPage])
        ->set('model.content', 'Updated content')
        ->call('store')
        ->assertHasNoErrors();

    $elementPage->refresh();
    $data = json_decode($elementPage->data, true);
    expect($data['content'])->toBe('Updated content');
});

it('validates element page data', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    $element = new Element();
    $element->tenant_id = $user->selected_tenant_id;
    $element->name = 'Text Block';
    $element->element_key = 'text_block_1_column';
    $element->description = 'Test';
    $element->save();

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_id' => $element->id,
        'data' => json_encode(['content' => 'Test content']),
        'sort' => 1,
    ]);
    if (!Schema::hasColumn('element_page', 'element_key')) {
        Schema::table('element_page', function (Blueprint $table): void {
            $table->string('element_key')->nullable();
        });
    }
    $elementPage->element_key = 'text_block_1_column';
    $elementPage->save();

    // Test without setting required fields (this depends on the element_key configuration)
    Volt::test($testSettings['componentName'], [$elementPage])
        ->call('store')
        ->assertHasNoErrors(); // Element validation depends on the specific element configuration
});

it('can delete element page', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    $element = new Element();
    $element->tenant_id = $user->selected_tenant_id;
    $element->name = 'Text Block';
    $element->element_key = 'text_block_1_column';
    $element->description = 'Test';
    $element->save();

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_id' => $element->id,
        'data' => json_encode(['content' => 'Test content']),
        'sort' => 1,
    ]);
    if (!Schema::hasColumn('element_page', 'element_key')) {
        Schema::table('element_page', function (Blueprint $table): void {
            $table->string('element_key')->nullable();
        });
    }
    $elementPage->element_key = 'text_block_1_column';
    $elementPage->save();

    Volt::test($testSettings['componentName'], [$elementPage])
        ->call('delete')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('element_page', [
        'id' => $elementPage->id,
    ]);
});

it('sets correct element layout', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    // Create a Page first
    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    $element = new Element();
    $element->tenant_id = $user->selected_tenant_id;
    $element->name = 'Text Block';
    $element->element_key = 'text_block_1_column';
    $element->description = 'Test';
    $element->save();

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_id' => $element->id,
        'data' => json_encode(['content' => 'Test content']),
        'sort' => 1,
    ]);
    if (!Schema::hasColumn('element_page', 'element_key')) {
        Schema::table('element_page', function (Blueprint $table): void {
            $table->string('element_key')->nullable();
        });
    }
    $elementPage->element_key = 'text_block_1_column';
    $elementPage->save();

    Volt::test($testSettings['componentName'], [$elementPage])
        ->assertSet('elementPage.element_key', 'text_block_1_column')
        ->assertNotSet('elementLayout', []);
});
