<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Helpers\FieldHelper;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\Content;
use Noerd\Cms\Models\ContentElement;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\ContentDocumentService;
use Noerd\Cms\Services\ElementCollectionService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Cms\Tests\Traits\CreatesElementFixtures;
use Noerd\Enums\Profile;
use Noerd\Models\SetupLanguage;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class, CreatesElementFixtures::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->createElementFixtures();
    $this->service = app(ContentDocumentService::class);
    $this->page = Page::factory()->create(['tenant_id' => $this->tenantId, 'name' => ['en' => 'Home']]);
});

afterEach(function (): void {
    $this->removeElementFixtures();
});

function zzText(Content $content, int $sort, array $text): ContentElement
{
    return ContentElement::create(['content_id' => $content->id, 'element_key' => 'rich_text', 'sort' => $sort, 'data' => ['text' => $text]]);
}

function zzElement(Content $content, int $sort, string $key, array $data = []): ContentElement
{
    return ContentElement::create(['content_id' => $content->id, 'element_key' => $key, 'sort' => $sort, 'data' => $data]);
}

it('loads running text and elements as sections in their order', function (): void {
    $text = zzText($this->page->content, 1, ['en' => '<p>Hello</p>', 'de' => '<p>Hallo</p>']);
    $image = zzElement($this->page->content, 2, $this->zzImageElementKey(), ['headline' => 'Pic', 'image' => 7]);

    $sections = $this->service->load($this->page->content);

    expect($sections)->toBe([
        ['kind' => 'text', 'uid' => 'r' . $text->id, 'id' => $text->id, 'text' => ['en' => '<p>Hello</p>', 'de' => '<p>Hallo</p>']],
        ['kind' => 'element', 'uid' => 'r' . $image->id, 'id' => $image->id, 'key' => $this->zzImageElementKey(), 'data' => ['headline' => 'Pic', 'image' => 7]],
    ])
        ->and($this->service->forEditor($sections, 'de')[0])->toBe(['kind' => 'text', 'uid' => 'r' . $text->id, 'html' => '<p>Hallo</p>']);
});

it('stores the edited language and keeps the other languages of the text', function (): void {
    $text = zzText($this->page->content, 1, ['en' => '<p>Hello</p>', 'de' => '<p>Hallo</p>']);
    $working = $this->service->load($this->page->content);

    $working = $this->service->apply($working, [
        ['kind' => 'text', 'uid' => 'r' . $text->id, 'html' => '<h2>Welcome</h2><p>Hello <strong>you</strong></p>'],
    ], 'en', $this->service->allowedKeys('cms.page'));
    $this->service->persist($this->page->content, $working);

    expect($text->fresh()->data['text'])->toBe(['en' => '<h2>Welcome</h2><p>Hello <strong>you</strong></p>', 'de' => '<p>Hallo</p>']);
});

it('splits the running text around a new element and stores the order', function (): void {
    $text = zzText($this->page->content, 1, ['en' => '<p>One</p><p>Two</p>', 'de' => '<p>Eins</p><p>Zwei</p>']);
    $working = $this->service->load($this->page->content);

    $working = $this->service->apply($working, [
        ['kind' => 'text', 'uid' => 'r' . $text->id, 'html' => '<p>One</p>'],
        ['kind' => 'element', 'uid' => 'nimage', 'key' => $this->zzImageElementKey(), 'data' => ['headline' => 'Between', 'image' => 3, 'evil' => 'x']],
        ['kind' => 'text', 'uid' => 'nrest', 'html' => '<p>Two</p>'],
    ], 'en', $this->service->allowedKeys('cms.page'));
    $this->service->persist($this->page->content, $working);

    $rows = $this->page->elements()->get();

    expect($rows->pluck('element_key')->all())->toBe(['rich_text', $this->zzImageElementKey(), 'rich_text'])
        ->and($rows->pluck('sort')->all())->toBe([1, 2, 3])
        ->and($rows[0]->id)->toBe($text->id)
        ->and($rows[0]->data['text'])->toBe(['en' => '<p>One</p>', 'de' => '<p>Eins</p><p>Zwei</p>'])
        ->and($rows[1]->data)->toBe(['headline' => 'Between', 'image' => 3])
        ->and($rows[2]->data['text'])->toBe(['en' => '<p>Two</p>']);
});

it('merges the other languages of a text section another one absorbed', function (): void {
    $first = zzText($this->page->content, 1, ['en' => '<p>A</p>', 'de' => '<p>A-de</p>']);
    $element = zzElement($this->page->content, 2, $this->zzImageElementKey(), ['headline' => 'X']);
    $second = zzText($this->page->content, 3, ['en' => '<p>B</p>', 'de' => '<p>B-de</p>']);
    $working = $this->service->load($this->page->content);

    $working = $this->service->apply($working, [
        ['kind' => 'text', 'uid' => 'r' . $first->id, 'absorbed' => ['r' . $second->id], 'html' => '<p>A</p><p>B</p>'],
    ], 'en', $this->service->allowedKeys('cms.page'));
    $this->service->persist($this->page->content, $working);

    expect($this->page->elements()->pluck('id')->all())->toBe([$first->id])
        ->and($first->fresh()->data['text'])->toBe(['en' => '<p>A</p><p>B</p>', 'de' => '<p>A-de</p><p>B-de</p>'])
        ->and(ContentElement::find($element->id))->toBeNull();
});

it('refuses elements the content type does not offer and never trusts a row id from the client', function (): void {
    $this->createScopedElementFixture();
    $foreign = zzText(Page::factory()->create(['tenant_id' => $this->tenantId])->content, 1, ['en' => '<p>Foreign</p>']);

    $working = $this->service->apply([], [
        ['kind' => 'element', 'uid' => 'nscoped', 'key' => $this->zzScopedElementKey(), 'data' => []],
        ['kind' => 'element', 'uid' => 'nunknown', 'key' => 'does_not_exist', 'data' => []],
        ['kind' => 'element', 'uid' => 'nrich', 'key' => 'rich_text', 'data' => []],
        ['kind' => 'text', 'uid' => 'r' . $foreign->id, 'id' => $foreign->id, 'html' => '<p>Mine</p>'],
    ], 'en', $this->service->allowedKeys('cms.page'));
    $this->service->persist($this->page->content, $working);

    expect($this->page->elements()->pluck('element_key')->all())->toBe(['rich_text'])
        ->and($foreign->fresh()->data['text'])->toBe(['en' => '<p>Foreign</p>'])
        ->and($this->page->elements()->first()->id)->not->toBe($foreign->id);
});

it('keeps an element the document holds although the content type no longer offers it', function (): void {
    $this->createScopedElementFixture();
    $kept = zzElement($this->page->content, 1, $this->zzScopedElementKey(), ['headline' => ['en' => 'Old']]);
    $working = $this->service->load($this->page->content);

    $working = $this->service->apply($working, [
        ['kind' => 'element', 'uid' => 'r' . $kept->id, 'key' => $this->zzScopedElementKey(), 'data' => ['headline' => ['en' => 'New']]],
    ], 'en', $this->service->allowedKeys('cms.page'));
    $this->service->persist($this->page->content, $working);

    expect($kept->fresh()->data['headline'])->toBe(['en' => 'New']);
});

it('sanitizes running text and rich text fields and drops empty text', function (): void {
    $working = $this->service->apply([], [
        ['kind' => 'text', 'uid' => 'nevil', 'html' => '<p>Ok</p><script>alert(1)</script><img src="x" onerror="alert(1)">'],
        ['kind' => 'element', 'uid' => 'ntext', 'key' => $this->zzTextElementKey(), 'data' => ['text' => ['en' => '<p onclick="x()">Hi</p><script>y()</script>']]],
        ['kind' => 'text', 'uid' => 'nempty', 'html' => '<p></p>'],
    ], 'en', $this->service->allowedKeys('cms.page'));
    $this->service->persist($this->page->content, $working);

    $rows = $this->page->elements()->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->data['text']['en'])->not->toContain('script')->not->toContain('onerror')->toContain('<p>Ok</p>')
        ->and($rows[1]->data['text']['en'])->toBe('<p>Hi</p>');
});

it('deletes removed elements through the model so their element collections go along', function (): void {
    $element = zzElement($this->page->content, 1, $this->zzCollectionElementKey(), ['headline' => ['en' => 'Team']]);
    $collection = app(ElementCollectionService::class)->ensure(ElementCollectionService::OWNER_ELEMENT_PAGE, $element->id, $this->tenantId, 'items', [], 'Team');

    $this->service->persist($this->page->content, $this->service->apply($this->service->load($this->page->content), [], 'en', []));

    expect(ContentElement::find($element->id))->toBeNull()
        ->and(Collection::find($collection->id))->toBeNull();
});

it('does not offer elements whose YAML says pickable: false', function (): void {
    $keys = collect(FieldHelper::getAllElementsGrouped('cms.page'))->flatten(1)->pluck('element_key');

    expect($keys)->not->toContain('rich_text')
        ->and($this->service->allowedKeys('cms.page'))->toContain($this->zzTextElementKey())->not->toContain('rich_text');
});

describe('page editor', function (): void {
    it('saves the document with the page — also a new page', function (): void {
        Livewire::test('cms::page-detail')
            ->set('detailData.name.en', 'Fresh')
            ->set('contentDocumentDraft', [
                'language' => 'en',
                'sections' => [
                    ['kind' => 'text', 'uid' => 'nintro', 'html' => '<h1>Fresh</h1><p>Typed right away</p>'],
                    ['kind' => 'element', 'uid' => 'nimg', 'key' => $this->zzImageElementKey(), 'data' => ['headline' => 'Pic']],
                ],
            ])
            ->call('store')
            ->assertHasNoErrors()
            ->assertDispatched('content-document-saved');

        $page = Page::query()->latest('id')->first();

        expect($page->elements()->pluck('element_key')->all())->toBe(['rich_text', $this->zzImageElementKey()])
            ->and($page->elements()->first()->data['text']['en'])->toBe('<h1>Fresh</h1><p>Typed right away</p>');
    });

    it('hands the document in the other language to the editor on a language switch', function (): void {
        zzText($this->page->content, 1, ['en' => '<p>Hello</p>', 'de' => '<p>Hallo</p>']);
        session([SetupLanguage::SESSION_KEY => 'en']);

        $component = Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail');
        $component->instance()->contentDocumentConfig();

        session([SetupLanguage::SESSION_KEY => 'de']);
        $component->dispatch('languageChanged')
            ->assertDispatched('content-document-load', fn(string $event, array $params): bool => $params['config']['language'] === 'de'
                && $params['config']['sections'][0]['html'] === '<p>Hallo</p>');
    });

    it('offers the catalog of the content type and never rich_text', function (): void {
        $config = Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail')->instance()->contentDocumentConfig();

        expect(array_column($config['catalog'], 'key'))->toContain($this->zzTextElementKey())->not->toContain('rich_text')
            ->and($config['editable'])->toBeTrue();
    });

    it('opens the media library as a picker for an image field', function (): void {
        Livewire::withUrlParams(['pageId' => $this->page->id])->test('cms::page-detail')
            ->call('pickContentDocumentMedia', 'cmsdoctoken1')
            ->assertDispatched('noerdModal');
    });

    it('shows the editor read-only to a read-only user', function (): void {
        $this->actingAsCmsUser(Profile::ReadOnly);
        $page = Page::factory()->create(['tenant_id' => $this->tenantId]);

        $config = Livewire::withUrlParams(['pageId' => $page->id])->test('cms::page-detail')->instance()->contentDocumentConfig();

        expect($config['editable'])->toBeFalse();
    });
});
