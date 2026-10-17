<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\PageCopyService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Home'], 'slug' => ['en' => '/home']]);
    $this->other = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Other'], 'slug' => ['en' => '/other']]);

    $this->first = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'text', 'sort' => 1, 'data' => ['text' => 'first']]);
    $this->second = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'text', 'sort' => 2, 'data' => ['text' => 'second']]);
    $this->foreign = ContentElement::create(['content_id' => $this->other->content_id, 'element_key' => 'text', 'sort' => 1, 'data' => []]);
});

function zzSortedKeys(Page $page): array
{
    return ContentElement::where('content_id', $page->content_id)->orderBy('sort')->pluck('data')->map(fn(array $data): string => $data['text'] ?? 'new')->all();
}

it('copies a page with its elements and unique slugs', function (): void {
    $copy = app(PageCopyService::class)->copy($this->page);

    expect($copy->id)->not->toBe($this->page->id)
        ->and($copy->name['en'])->toBe('Home 2')
        ->and($copy->slug['en'])->toBe('/home-2')
        ->and(zzSortedKeys($copy))->toBe(['first', 'second']);

    // A second copy uniquifies against the first copy.
    expect(app(PageCopyService::class)->copy($this->page)->slug['en'])->toBe('/home-2-2');
});

it('suffixes the data title when copying a collection entry', function (): void {
    $collection = Collection::create(['tenant_id' => $this->tenantId, 'collection_key' => 'PROJECTS', 'name' => 'Projects']);
    $entry = Page::factory()->create([
        'tenant_id' => $this->tenantId,
        'collection_id' => $collection->id,
        'name' => ['en' => 'Bridge'],
        'slug' => ['en' => '/bridge'],
        'data' => ['title' => ['en' => 'Bridge'], 'client' => 'ACME'],
    ]);

    $copy = app(PageCopyService::class)->copy($entry);

    expect($copy->data['title']['en'])->toBe('Bridge 2')
        ->and($copy->data['client'])->toBe('ACME')
        ->and($copy->collection_id)->toBe($collection->id);
});
