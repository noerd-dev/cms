<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\PageSlugService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->useOnlyLanguage('de');
    $this->addLanguage('en', 'English');
    $this->slugs = app(PageSlugService::class);
    $this->page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['de' => 'Seite'], 'slug' => ['de' => '/seite'], 'layout' => 'weblayout']);
});

it('brings a typed slug into the stored shape', function (string $typed, string $stored): void {
    expect($this->slugs->normalize($typed))->toBe($stored);
})->with([
    'missing leading slash' => ['ueber-uns', '/ueber-uns'],
    'upper case and trailing slash' => ['/Ueber-Uns/', '/ueber-uns'],
    'empty segments' => ['/a//b///c', '/a/b/c'],
    'query and fragment' => ['/kontakt?x=1#top', '/kontakt'],
    'spaces' => ['/über uns', '/über-uns'],
    'dot segments' => ['/a/../b/.', '/a/b'],
    'backslashes' => ['\\a\\b', '/a/b'],
    'root' => ['/', '/'],
]);

it('normalizes a slug typed into the page editor', function (): void {
    Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail')
        ->set('detailData.slug.de', 'Neue Seite/')
        ->call('store')
        ->assertHasNoErrors();

    expect($this->page->fresh()->slug['de'])->toBe('/neue-seite');
});

it('makes a slug unique across every language of the tenant', function (): void {
    Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'About'], 'slug' => ['en' => '/about']]);

    Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail')
        ->set('detailData.slug.de', '/about')
        ->call('store')
        ->assertHasNoErrors();

    expect($this->page->fresh()->slug['de'])->toBe('/about-2');
});

it('refuses a slug naming another host', function (string $slug): void {
    Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail')
        ->set('detailData.slug.de', $slug)
        ->call('store')
        ->assertHasErrors('detailData.slug');

    expect($this->page->fresh()->slug['de'])->toBe('/seite');
})->with([
    'protocol relative' => ['//evil.com'],
    'absolute' => ['https://evil.com/x'],
    'backslashes' => ['\\\\evil.com'],
]);

it('refuses a slug under a reserved prefix', function (): void {
    config(['cms.reserved_slug_prefixes' => ['/shop']]);

    foreach (['/livewire/update', '/cms', '/shop/cart'] as $slug) {
        Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail')
            ->set('detailData.slug.de', $slug)
            ->call('store')
            ->assertHasErrors('detailData.slug');
    }

    expect($this->page->fresh()->slug['de'])->toBe('/seite')
        ->and($this->slugs->violation('/cmsx'))->toBeNull();
});

it('normalizes and uniquifies article slugs and refuses another host', function (): void {
    Article::factory()->create(['tenant_id' => $this->tenantId, 'title' => ['de' => 'Alt'], 'slug' => ['en' => '/news']]);
    $article = Article::factory()->create(['tenant_id' => $this->tenantId, 'title' => ['de' => 'Neu'], 'slug' => ['de' => '/neu']]);

    Livewire::test('cms::cms-article-detail', ['modelId' => $article->id])
        ->set('detailData.slug.de', 'News/')
        ->call('store')
        ->assertHasNoErrors();

    expect($article->fresh()->slug['de'])->toBe('/news-2');

    Livewire::test('cms::cms-article-detail', ['modelId' => $article->id])
        ->set('detailData.slug.de', '//evil.com')
        ->call('store')
        ->assertHasErrors('detailData.slug.de');

    expect($article->fresh()->slug['de'])->toBe('/news-2');
});

it('reports stored slugs that break the rules without changing them', function (): void {
    Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['de' => 'Alt'], 'slug' => ['de' => '//evil.com']]);
    Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['de' => 'Doppelt'], 'slug' => ['en' => '/seite']]);

    Artisan::call('cms:check-slugs');
    $output = Artisan::output();

    expect($output)->toContain('//evil.com')
        ->toContain('also used by page ' . $this->page->id)
        ->and(Page::query()->where('slug->de', '//evil.com')->exists())->toBeTrue();
});
