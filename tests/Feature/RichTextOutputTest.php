<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\CollectionEntryStore;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Services\PageElementService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

/*
 | Element templates print rich text raw ({!! $element->text !!}). Whatever
 | wrote the stored value — an older version, an import, the database — the
 | page data the website renders is sanitized, and the save paths of
 | collection entries and element-collection rows sanitize as well.
 */
beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->createElementFixtures();
    $this->createCustomElementFixture('zz-fixture-rich-rows', <<<'YAML'
        title: 'Zz Rich Rows'
        fields:
          - name: detailData.title
            label: Title
            type: translatableText
            colspan: 12
          - name: detailData.slides
            label: Slides
            type: elementCollection
            colspan: 12
            fields:
              - name: body
                label: Body
                type: translatableRichText
                colspan: 12
        YAML);

    $this->page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Home'], 'slug' => ['en' => '/home']]);
    $this->rowFields = [['name' => 'body', 'label' => 'Body', 'type' => 'translatableRichText', 'colspan' => 12]];
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

it('sanitizes rich text of an element that was stored unsanitized', function (): void {
    ContentElement::create([
        'content_id' => $this->page->content_id,
        'element_key' => $this->zzTextElementKey(),
        'sort' => 1,
        'data' => ['text' => ['en' => '<p>Hi</p><img src="x" onerror="alert(1)"><script>alert(2)</script>']],
    ]);

    $elements = app(PageElementService::class)->processPageElements($this->page->fresh(), 'en');

    expect($elements[0]['data']->text)->not->toContain('onerror')
        ->not->toContain('<script')
        ->toContain('<p>Hi</p>');
});

it('sanitizes rich text of element-collection rows on output', function (): void {
    $element = ContentElement::create([
        'content_id' => $this->page->content_id,
        'element_key' => 'zz_fixture_rich_rows',
        'sort' => 1,
        'data' => ['title' => ['en' => 'Slides']],
    ]);

    $collection = app(ElementCollectionService::class)->ensure(
        ElementCollectionService::OWNER_ELEMENT_PAGE,
        $element->id,
        $this->tenantId,
        'slides',
        $this->rowFields,
        'Slides',
    );
    // Written past the store, as an older version or an import would have.
    Page::create([
        'tenant_id' => $this->tenantId,
        'collection_id' => $collection->id,
        'data' => ['body' => ['en' => '<b>Bold</b><img src=x onerror="alert(1)">']],
        'sort' => 0,
    ]);

    $elements = app(PageElementService::class)->processPageElements($this->page->fresh(), 'en');

    expect($elements[0]['data']->slides[0]['body'])->toContain('<b>Bold</b>')
        ->not->toContain('onerror');
});

it('sanitizes rich text of an element-collection row on save', function (): void {
    $element = ContentElement::create(['content_id' => $this->page->content_id, 'element_key' => 'zz_fixture_rich_rows', 'sort' => 1, 'data' => []]);
    $service = app(ElementCollectionService::class);
    $collection = $service->ensure(ElementCollectionService::OWNER_ELEMENT_PAGE, $element->id, $this->tenantId, 'slides', $this->rowFields, 'Slides');

    $row = app(CollectionEntryStore::class)->persistRow(
        $collection,
        $service->schemaFor($collection),
        ['body' => ['en' => '<p>Ok</p><img src=x onerror="alert(1)">']],
        null,
    );

    expect($row->fresh()->data['body']['en'])->toContain('<p>Ok</p>')
        ->not->toContain('onerror');
});

it('sanitizes rich text of a collection entry on save', function (): void {
    $definition = Noerd\Cms\Models\CollectionDefinition::create([
        'tenant_id' => $this->tenantId,
        'filename' => 'notes',
        'key' => 'NOTES',
        'title' => 'Note',
        'title_list' => 'Notes',
        'has_page' => false,
        'fields' => [
            ['name' => 'detailData.body', 'label' => 'Body', 'type' => 'translatableRichText', 'colspan' => 12],
        ],
    ]);

    $entry = app(CollectionEntryStore::class)->persist(
        'notes',
        ['hasPage' => false, 'fields' => $definition->fields],
        ['body' => ['en' => '<p>Note</p><script>alert(1)</script>']],
        null,
        $this->tenantId,
        $this->user->id,
        'en',
    );

    expect($entry->fresh()->data['body']['en'])->toContain('<p>Note</p>')
        ->not->toContain('<script');
});
