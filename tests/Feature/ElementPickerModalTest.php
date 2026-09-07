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

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->createElementFixtures();
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('lists the discovered elements grouped and hands the pick back to the opener', function (): void {
    Livewire::test('cms::element-picker-modal', ['token' => 'insert-at-1'])
        ->assertSee('Zz Test')
        ->assertSee('Zz Fixture Text')
        ->call('pick', $this->zzTextElementKey())
        ->assertDispatched('elementPicked', elementKey: $this->zzTextElementKey(), token: 'insert-at-1')
        ->assertDispatched('closeTopModal');
});

it('appends exactly one element to the page for a pick', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Home'], 'slug' => ['en' => '/home']]);

    Livewire::withUrlParams(['pageId' => $page->id])
        ->test('cms::page-detail')
        ->call('addElement', $this->zzTextElementKey(), 'insert-end');

    $elements = ElementPage::where('page_id', $page->id)->get();
    expect($elements)->toHaveCount(1)
        ->and($elements->first()->element_key)->toBe($this->zzTextElementKey());
});
