<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Support\ContentText;
use Noerd\Cms\Support\ContentTypeRegistry;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('creates the content of a page and keeps the name on it', function (): void {
    $page = Page::create(['name' => ['en' => 'About'], 'slug' => ['en' => '/about']]);

    $content = Content::find($page->content_id);

    expect($content)->not->toBeNull()
        ->and($content->type)->toBe('cms.page')
        ->and($content->tenant_id)->toBe($this->tenantId)
        ->and($content->name)->toBe(['en' => 'About'])
        ->and($page->fresh()->name)->toBe(['en' => 'About'])
        ->and($page->fresh()->toArray()['name'])->toBe(['en' => 'About'])
        ->and($page->fresh()->toArray())->not->toHaveKey('content');

    $page->fresh()->update(['name' => ['en' => 'About us']]);

    expect($content->fresh()->name)->toBe(['en' => 'About us']);
});

it('deletes the content and its elements with the page', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenantId]);
    ContentElement::create(['content_id' => $page->content_id, 'element_key' => 'text', 'sort' => 1, 'data' => []]);

    $page->delete();

    expect(Content::find($page->content_id))->toBeNull()
        ->and(ContentElement::where('content_id', $page->content_id)->exists())->toBeFalse();
});

it('gives a replicated page a content of its own with copies of the elements', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Home']]);
    ContentElement::create(['content_id' => $page->content_id, 'element_key' => 'text', 'sort' => 1, 'data' => ['text' => 'Hello']]);

    $copy = $page->replicate();
    $copy->save();

    expect($copy->content_id)->not->toBe($page->content_id)
        ->and($copy->fresh()->name)->toBe(['en' => 'Home'])
        ->and($copy->elements()->pluck('data')->all())->toBe([['text' => 'Hello']])
        ->and($page->elements()->count())->toBe(1);
});

it('resolves the extension record of a content through the registry', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenantId]);

    expect($page->content->extension()?->is($page))->toBeTrue()
        ->and(app(ContentTypeRegistry::class)->forType('cms.page')?->modelClass)->toBe(Page::class)
        ->and(app(ContentTypeRegistry::class)->forModel(Page::class)?->detailRoute)->toBe('cms.page.detail');
});

it('searches and sorts the pages list by the content name', function (): void {
    Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Zebra'], 'slug' => ['en' => '/zebra']]);
    Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Aardvark'], 'slug' => ['en' => '/aardvark']]);

    $component = Livewire::test('cms::pages-list')->set('listFilters.language', 'en');

    $component->set('search', 'Zeb');
    expect(collect($component->instance()->listData()['rows']->items())->pluck('name')->all())->toBe(['Zebra']);

    $component->set('search', '')->set('sortField', 'name')->set('sortAsc', true);
    expect(collect($component->instance()->listData()['rows']->items())->pluck('name')->all())->toBe(['Aardvark', 'Zebra']);
});

it('offers an element only to the content types its YAML names', function (): void {
    $this->createElementFixtures();
    $this->createScopedElementFixture();

    $keysFor = fn(string $type): array => collect(FieldHelper::getAllElementsGrouped($type))->flatten(1)->pluck('element_key')->all();

    expect($keysFor('zz.intranet'))->toContain($this->zzScopedElementKey())
        ->not->toContain($this->zzTextElementKey())
        ->and($keysFor('cms.page'))->toContain($this->zzTextElementKey())
        ->not->toContain($this->zzScopedElementKey());
});

it('reads the text of a content from the text fields of its elements', function (): void {
    $this->createElementFixtures();
    $this->createScopedElementFixture();

    $content = Content::create(['tenant_id' => $this->tenantId, 'type' => 'zz.intranet', 'name' => ['en' => 'Handbook']]);
    ContentElement::create(['content_id' => $content->id, 'element_key' => $this->zzScopedElementKey(), 'sort' => 1, 'data' => [
        'headline' => ['en' => 'Holidays', 'de' => 'Urlaub'],
        'body' => ['en' => 'Ask your lead two weeks ahead.'],
        'image' => 17,
    ]]);
    ContentElement::create(['content_id' => $content->id, 'element_key' => $this->zzTextElementKey(), 'sort' => 2, 'data' => [
        'text' => ['en' => '<h2>Sick leave</h2><p>Call in before 9 &amp; send a note.</p>'],
    ]]);

    expect(ContentText::markdown($content))->toBe(
        "# Handbook\n\nHolidays\n\nAsk your lead two weeks ahead.\n\n### Sick leave\n\nCall in before 9 & send a note.",
    );
});

it('moves existing pages and their elements onto contents', function (): void {
    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_16_000001_create_contents_table.php';
    $migration->down();

    $pageId = DB::table('cms_pages')->insertGetId([
        'tenant_id' => $this->tenantId,
        'name' => json_encode(['en' => 'Legacy']),
        'slug' => json_encode(['en' => '/legacy']),
        'is_active' => true,
    ]);
    $elementId = DB::table('cms_page_elements')->insertGetId([
        'page_id' => $pageId,
        'element_key' => 'text',
        'sort' => 1,
        'data' => json_encode(['text' => 'Kept']),
    ]);

    $migration->up();

    $page = Page::find($pageId);

    expect($page->name)->toBe(['en' => 'Legacy'])
        ->and($page->content->type)->toBe('cms.page')
        ->and($page->elements()->pluck('id')->all())->toBe([$elementId])
        ->and(ContentElement::find($elementId)->data)->toBe(['text' => 'Kept']);
});
