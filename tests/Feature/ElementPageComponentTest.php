<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

$testSettings = [
    'componentName' => 'element-page-detail',
    'listName' => 'element-pages-list',
    'id' => 'modelId',
];

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    $this->createElementFixtures();

    $page = Page::create([
        'name' => ['en' => 'Test Page'],
        'slug' => ['en' => 'test-page'],
        'tenant_id' => $this->tenant->id,
    ]);

    $this->page = $page;
    $this->elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => $this->zzTextElementKey(),
        'data' => ['content' => 'Test content'],
        'sort' => 1,
    ]);
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('successfully mounts with element page', function () use ($testSettings): void {
    Livewire::test($testSettings['componentName'], ['modelId' => $this->elementPage->id])
        ->assertSet('modelId', $this->elementPage->id)
        ->assertSet('elementKey', $this->zzTextElementKey())
        ->assertHasNoErrors();
});

it('can delete element page', function () use ($testSettings): void {
    Livewire::test($testSettings['componentName'], ['modelId' => $this->elementPage->id])
        ->call('delete')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('cms_page_elements', [
        'id' => $this->elementPage->id,
    ]);
});

it('uses passed modelId instead of URL id parameter', function () use ($testSettings): void {
    // Simulate the bug: URL has ?id=999 (Page ID), but component receives actual ElementPage ID
    Livewire::withUrlParams(['id' => 999])
        ->test($testSettings['componentName'], ['modelId' => $this->elementPage->id])
        ->assertSet('modelId', $this->elementPage->id)
        ->assertSet('elementKey', $this->zzTextElementKey())
        ->call('store')
        ->assertHasNoErrors();
});

it('delete handles non-existent element page gracefully', function () use ($testSettings): void {
    // Mount with valid element, then set modelId to non-existent ID
    Livewire::test($testSettings['componentName'], ['modelId' => $this->elementPage->id])
        ->set('modelId', 99999)
        ->call('delete')
        ->assertHasNoErrors();

    // Original element should still exist
    $this->assertDatabaseHas('cms_page_elements', ['id' => $this->elementPage->id]);
});

it('shows a content error when element layout is missing', function () use ($testSettings): void {
    $elementPage = ElementPage::create([
        'page_id' => $this->page->id,
        'element_key' => '____missing____',
        'data' => ['foo' => 'bar'],
        'sort' => 2,
    ]);

    Livewire::test($testSettings['componentName'], ['modelId' => $elementPage->id])
        ->assertSee(__('Element component not found:'))
        ->assertSee(__('Please create both the .yml and .blade.php files in the elements folder.'))
        ->assertSee('____missing____');
});
