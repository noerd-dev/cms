<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ContentDocumentService;
use Noerd\Cms\Services\Maintenance\SelectOptionValueRepair;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;
use Noerd\Media\Models\Media;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

/*
 | The editor's draft is client input. These tests send what a forged request
 | would send and prove only values of the element's contract get through —
 | and that saving never destroys data the editor cannot show.
 */
beforeEach(function (): void {
    Storage::fake('media');
    $this->actingAsCmsUser();
    $this->createElementFixtures();
    $this->createCustomElementFixture('zz-fixture-select', <<<'YAML'
        title: 'Zz Select'
        fields:
          - name: detailData.parent
            label: Parent
            type: select
            options:
              - ''
              - 'Projects'
              - 'Services'
            colspan: 6
          - name: detailData.title
            label: Title
            type: translatableText
            colspan: 6
        YAML);
    $this->service = app(ContentDocumentService::class);
    $this->page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Home'], 'slug' => ['en' => '/home']]);
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

function zzElementDraft(object $test, array $data, string $key = 'zz_fixture_select'): array
{
    $working = $test->service->apply([], [
        ['kind' => 'element', 'uid' => 'nnew', 'key' => $key, 'data' => $data],
    ], 'en', $test->service->allowedKeys('cms.page'));

    return $working[0]['data'];
}

it('offers and stores a select option as its value, never its index', function (): void {
    $definition = $this->service->definition('zz_fixture_select');

    expect(array_column($definition['fields'][0]['options'], 'value'))->toBe(['', 'Projects', 'Services'])
        ->and(zzElementDraft($this, ['parent' => 'Services'])['parent'])->toBe('Services');
});

it('refuses a select value that is not one of the options', function (): void {
    expect(zzElementDraft($this, ['parent' => '<script>'])['parent'])->toBe('')
        ->and(zzElementDraft($this, ['parent' => '2'])['parent'])->toBe('');
});

it('takes translations only in an active language of the tenant', function (): void {
    expect(zzElementDraft($this, ['title' => ['en' => 'Hello', 'xx' => 'Injected']])['title'])->toBe(['en' => 'Hello']);
});

it('takes an image only as a media id of the tenant', function (): void {
    $own = Media::factory()->file($this->tenantId, 'own.jpg')->create();
    $foreign = Media::factory()->file(Tenant::factory()->create()->id, 'foreign.jpg')->create();
    $key = $this->zzImageElementKey();

    expect(zzElementDraft($this, ['image' => $own->id], $key)['image'])->toBe($own->id)
        ->and(zzElementDraft($this, ['image' => $foreign->id], $key)['image'])->toBeNull()
        ->and(zzElementDraft($this, ['image' => 'javascript:alert(1)'], $key)['image'])->toBeNull();
});

it('keeps a stored legacy image path the client sends back unchanged', function (): void {
    $element = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => $this->zzImageElementKey(), 'sort' => 1, 'data' => ['headline' => 'Old', 'image' => '/storage/old.jpg']]);
    $working = $this->service->load($this->page->content);

    $working = $this->service->apply($working, [
        ['kind' => 'element', 'uid' => 'r' . $element->id, 'key' => $this->zzImageElementKey(), 'data' => ['headline' => 'New', 'image' => '/storage/old.jpg']],
    ], 'en', $this->service->allowedKeys('cms.page'));

    expect($working[0]['data'])->toBe(['headline' => 'New', 'image' => '/storage/old.jpg']);
});

it('refuses a draft written in a language the tenant does not run', function (): void {
    $text = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'rich_text', 'sort' => 1, 'data' => ['text' => ['en' => '<p>Kept</p>']]]);

    Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail')
        ->set('contentDocumentDraft', ['language' => 'xx', 'sections' => [['kind' => 'text', 'uid' => 'r' . $text->id, 'html' => '<p>Injected</p>']]])
        ->call('store')
        ->assertHasNoErrors();

    expect($text->fresh()->data['text'])->toBe(['en' => '<p>Kept</p>']);

    // The same draft in an active language goes through.
    Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail')
        ->set('contentDocumentDraft', ['language' => 'en', 'sections' => [['kind' => 'text', 'uid' => 'r' . $text->id, 'html' => '<p>Edited</p>']]])
        ->call('store')
        ->assertHasNoErrors();

    expect($text->fresh()->data['text'])->toBe(['en' => '<p>Edited</p>']);
});

it('takes at most MAX_SECTIONS sections from a draft', function (): void {
    $draft = array_fill(0, ContentDocumentService::MAX_SECTIONS + 5, ['kind' => 'text', 'html' => '<p>x</p>']);

    expect($this->service->apply([], $draft, 'en', []))->toHaveCount(ContentDocumentService::MAX_SECTIONS);
});

it('keeps the data of an element whose YAML is unknown when the document is saved', function (): void {
    $retired = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'zz_retired_element', 'sort' => 1, 'data' => ['headline' => 'Keep me', 'nested' => ['a' => 1]]]);
    $working = $this->service->load($this->page->content);

    // The client cannot show the element's fields and sends whatever it likes.
    $working = $this->service->apply($working, [
        ['kind' => 'element', 'uid' => 'r' . $retired->id, 'key' => 'zz_retired_element', 'data' => ['headline' => 'Overwritten']],
    ], 'en', $this->service->allowedKeys('cms.page'));
    $this->service->persist($this->page->content, $working);

    expect($retired->fresh()->data)->toBe(['headline' => 'Keep me', 'nested' => ['a' => 1]]);
});

it('keeps a plain legacy value of a translatable field as the default language', function (): void {
    $element = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'zz_fixture_select', 'sort' => 1, 'data' => ['parent' => 'Projects', 'title' => 'Written before translations']]);

    $this->service->persist($this->page->content, $this->service->load($this->page->content));

    expect($element->fresh()->data['title'])->toBe(['en' => 'Written before translations']);
});

it('rewrites stored select indexes to the option value, once', function (): void {
    $index = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'zz_fixture_select', 'sort' => 1, 'data' => ['parent' => '2', 'title' => ['en' => 'A']]]);
    $value = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'zz_fixture_select', 'sort' => 2, 'data' => ['parent' => 'Projects']]);
    $empty = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'zz_fixture_select', 'sort' => 3, 'data' => ['parent' => '0']]);
    $outOfRange = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'zz_fixture_select', 'sort' => 4, 'data' => ['parent' => '7']]);

    $repair = app(SelectOptionValueRepair::class);

    expect($repair->run(dryRun: true))->toBe(2)
        ->and($index->fresh()->data['parent'])->toBe('2')
        ->and($repair->run())->toBe(2)
        ->and($index->fresh()->data)->toBe(['parent' => 'Services', 'title' => ['en' => 'A']])
        ->and($value->fresh()->data['parent'])->toBe('Projects')
        ->and($empty->fresh()->data['parent'])->toBe('')
        ->and($outOfRange->fresh()->data['parent'])->toBe('7')
        ->and($repair->run())->toBe(0);
});

it('leaves a list whose options are numbers themselves alone', function (): void {
    expect(app(SelectOptionValueRepair::class)->repair(['size' => '1'], ['size' => ['1', '2', '3']]))->toBe(['size' => '1'])
        ->and(app(SelectOptionValueRepair::class)->repair(['size' => '1'], ['size' => ['S', 'M']]))->toBe(['size' => 'M']);
});
