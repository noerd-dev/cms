<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ContentElementEditorService;
use Noerd\Cms\Services\PageCopyService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->editor = app(ContentElementEditorService::class);
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

it('appends an element after the last one or inserts it at a position', function (): void {
    $appended = $this->editor->addElement($this->page->content, 'text');
    expect($appended->sort)->toBe(3);

    $inserted = $this->editor->addElement($this->page->content, 'text', 1);
    expect($inserted->sort)->toBe(1)
        ->and($this->first->fresh()->sort)->toBe(2)
        ->and($this->second->fresh()->sort)->toBe(3)
        ->and($appended->fresh()->sort)->toBe(4);
});

it('re-sequences the elements on move', function (): void {
    $this->editor->move($this->page->content, $this->second->id, 0);

    expect(zzSortedKeys($this->page))->toBe(['second', 'first']);
});

it('duplicates an element right after the original', function (): void {
    $copy = $this->editor->duplicate($this->page->content, $this->first->id);

    expect($copy)->not->toBeNull()
        ->and($copy->data)->toBe(['text' => 'first'])
        ->and(zzSortedKeys($this->page))->toBe(['first', 'first', 'second']);
});

it('refuses to duplicate or delete an element of another content', function (): void {
    expect($this->editor->duplicate($this->page->content, $this->foreign->id))->toBeNull()
        ->and($this->editor->delete($this->page->content, $this->foreign->id))->toBeFalse()
        ->and($this->foreign->fresh())->not->toBeNull();
});

it('deletes an element of the page', function (): void {
    expect($this->editor->delete($this->page->content, $this->first->id))->toBeTrue()
        ->and(zzSortedKeys($this->page))->toBe(['second']);
});

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
