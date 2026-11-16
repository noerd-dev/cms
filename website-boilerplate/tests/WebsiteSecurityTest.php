<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Website\Middleware\WebsiteMiddleware;
use Noerd\Website\Models\ElementPage;
use Noerd\Website\Models\Language;
use Noerd\Website\Models\Navigation;
use Noerd\Website\Models\Page;
use Noerd\Website\Models\Tenant;
use Noerd\Website\Services\WebsiteService;
use Noerd\Website\Support\WebsiteLanguage;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/*
 | The public website renders editor data to anonymous visitors. These tests
 | act like an attacker — a guessed id, another tenant, a stored script, a
 | forged link — and prove none of it reaches the page.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    Language::factory()->default()->create(['tenant_id' => $this->tenant->id, 'code' => 'de', 'name' => 'Deutsch']);
});

function zzWebsitePage(object $test, array $attributes = []): Page
{
    return Page::factory()->create(array_merge([
        'tenant_id' => $test->tenant->id,
        'name' => ['de' => 'Seite'],
        'slug' => ['de' => '/seite'],
        'is_active' => true,
    ], $attributes));
}

/**
 * The rendered markup without Livewire's snapshot attribute, which carries
 * the (escaped) component state — editor data as data, not as markup.
 */
function zzMarkup(object $component): string
{
    return (string) preg_replace('/wire:snapshot="[^"]*"/', '', $component->html());
}

describe('page lookups', function (): void {
    it('has no route that serves a page by its id', function (): void {
        $page = zzWebsitePage($this, ['is_active' => false]);

        expect(Route::has('website.page'))->toBeFalse();

        $this->get('/page/' . $page->id . '?uuid=' . $this->tenant->uuid)->assertNotFound();
    });

    it('answers 404 for an inactive homepage', function (): void {
        $page = zzWebsitePage($this, ['is_active' => false]);
        CmsSetting::factory()->create(['tenant_id' => $this->tenant->id, 'homepage_page_id' => $page->id]);

        $this->get('/index?uuid=' . $this->tenant->uuid)->assertNotFound();
    });

    it('answers 404 without cms settings', function (): void {
        $this->get('/index?uuid=' . $this->tenant->uuid)->assertNotFound();
    });

    it('never renders a homepage of another tenant', function (): void {
        $foreign = Page::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'is_active' => true]);
        CmsSetting::factory()->create(['tenant_id' => $this->tenant->id, 'homepage_page_id' => $foreign->id]);

        $this->get('/index?uuid=' . $this->tenant->uuid)->assertNotFound();
    });

    it('does not take the tenant uuid from a request body', function (): void {
        $first = Tenant::query()->orderBy('id')->first();
        $other = Tenant::factory()->create();
        $captured = null;

        Route::post('/_test/website-body-uuid', function (Request $request) use (&$captured) {
            $captured = $request->attributes->get('tenant_id');

            return response()->json(['ok' => true]);
        })->middleware(WebsiteMiddleware::class);

        $this->post('/_test/website-body-uuid', ['uuid' => $other->uuid])->assertOk();

        expect($captured)->toBe($first->id);
    });

    it('never redirects to another host', function (): void {
        $target = zzWebsitePage($this, ['slug' => ['de' => '//evil.com']]);
        \Noerd\Cms\Models\Redirect::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'source_path' => '/alt',
            'target_page_id' => $target->id,
            'is_active' => true,
        ]);

        $this->get('/alt?uuid=' . $this->tenant->uuid)->assertNotFound();
    });

    it('resolves a managed redirect to a page of the site', function (): void {
        $target = zzWebsitePage($this, ['slug' => ['de' => '/neu']]);
        \Noerd\Cms\Models\Redirect::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'source_path' => '/alt',
            'target_page_id' => $target->id,
            'is_active' => true,
        ]);

        $this->get('/alt?uuid=' . $this->tenant->uuid)->assertRedirect('/neu');
    });

    it('lists only the active entries of a collection, of its own tenant', function (): void {
        $collection = \Noerd\Website\Models\Collection::query()->create(['tenant_id' => $this->tenant->id, 'collection_key' => 'NEWS', 'name' => 'News']);
        Page::factory()->create(['tenant_id' => $this->tenant->id, 'collection_id' => $collection->id, 'data' => json_encode(['title' => 'Visible']), 'is_active' => true, 'slug' => null]);
        Page::factory()->create(['tenant_id' => $this->tenant->id, 'collection_id' => $collection->id, 'data' => json_encode(['title' => 'Hidden']), 'is_active' => false, 'slug' => null]);

        expect(array_column($collection->entries(), 'title'))->toBe(['Visible']);
    });
});

describe('element output', function (): void {
    it('encodes a lightbox URL with a quote for the Alpine expression', function (): void {
        $markup = zzMarkup(Livewire::test('website::elements.images-block-2-column', ['data' => [
            'image' => '/a.jpg',
            'videoUrl' => "https://video.test/v');alert(document.cookie);('",
        ]]));

        expect($markup)->toContain('openLightbox(')
            ->not->toContain("v');alert")
            ->not->toContain("v&#039;);alert")
            ->toContain('\\u0027');
    });

    it('neutralises a javascript: link in a button and escapes its text', function (): void {
        $markup = zzMarkup(Livewire::test('website::elements.button-link', ['data' => ['link' => 'javascript:alert(1)', 'text' => '<b>Click</b>']]));

        expect($markup)->not->toContain('javascript:')
            ->toContain('href="#"')
            ->not->toContain('<b>Click</b>');
    });

    it('neutralises a javascript: link and a CSS break-out in an image block', function (): void {
        $markup = zzMarkup(Livewire::test('website::elements.image-block-1-column', ['data' => [
            'videoUrl' => 'javascript:alert(1)',
            'image' => "/a.jpg');background:url('javascript:alert(1)",
        ]]));

        expect($markup)->not->toContain('href="javascript:')
            ->not->toContain("a.jpg&#039;)")
            ->not->toContain("a.jpg')")
            ->toContain('a.jpg%27%29');
    });

    it('sanitizes stored rich text of an element on the page', function (): void {
        $page = zzWebsitePage($this, ['slug' => ['de' => '/xss']]);
        ElementPage::create([
            'content_id' => $page->content_id,
            'element_key' => 'text_block_1_column',
            'sort' => 1,
            'data' => json_encode(['text' => ['de' => '<p>Hallo</p><img src="x" onerror="alert(1)">']]),
        ]);

        $this->get('/xss?uuid=' . $this->tenant->uuid)
            ->assertOk()
            ->assertSee('Hallo')
            ->assertDontSee('onerror', false);
    });

    it('prints the page meta into the head', function (): void {
        zzWebsitePage($this, [
            'slug' => ['de' => '/meta'],
            'meta_title' => json_encode(['de' => 'Meta Titel']),
            'meta_description' => json_encode(['de' => 'Beschreibung "mit" Zeichen']),
            'meta_noindex' => true,
        ]);

        $this->get('/meta?uuid=' . $this->tenant->uuid)
            ->assertOk()
            ->assertSee('<title>Meta Titel</title>', false)
            ->assertSee('<meta name="description" content="Beschreibung &quot;mit&quot; Zeichen">', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    });
});

describe('navigation', function (): void {
    it('drops unsafe links, inactive and foreign pages and nests children by sort order', function (): void {
        $active = zzWebsitePage($this, ['slug' => ['de' => '/aktiv']]);
        $inactive = zzWebsitePage($this, ['slug' => ['de' => '/inaktiv'], 'is_active' => false]);
        $foreign = Page::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'slug' => ['de' => '/fremd'], 'is_active' => true]);

        $parent = Navigation::factory()->create(['tenant_id' => $this->tenant->id, 'navigation_key' => 'MAIN', 'name' => json_encode(['de' => 'Eltern']), 'sort_order' => 1]);
        Navigation::factory()->create(['tenant_id' => $this->tenant->id, 'navigation_key' => 'MAIN', 'name' => json_encode(['de' => 'Zweites Kind']), 'parent_id' => $parent->id, 'sort_order' => 2, 'page_id' => $active->id]);
        Navigation::factory()->create(['tenant_id' => $this->tenant->id, 'navigation_key' => 'MAIN', 'name' => json_encode(['de' => 'Erstes Kind']), 'parent_id' => $parent->id, 'sort_order' => 1, 'link' => '/erstes']);
        Navigation::factory()->create(['tenant_id' => $this->tenant->id, 'navigation_key' => 'MAIN', 'name' => json_encode(['de' => 'Böse']), 'sort_order' => 2, 'link' => 'javascript:alert(1)']);
        Navigation::factory()->create(['tenant_id' => $this->tenant->id, 'navigation_key' => 'MAIN', 'name' => json_encode(['de' => 'Inaktiv']), 'sort_order' => 3, 'page_id' => $inactive->id]);
        Navigation::factory()->create(['tenant_id' => $this->tenant->id, 'navigation_key' => 'MAIN', 'name' => json_encode(['de' => 'Fremd']), 'sort_order' => 4, 'page_id' => $foreign->id]);

        $navigation = (new WebsiteService())->getNavigation($this->tenant->id, 'main', 'de');

        expect(array_column($navigation, 'name'))->toBe(['Eltern'])
            ->and(array_column($navigation[0]['children'], 'name'))->toBe(['Erstes Kind', 'Zweites Kind'])
            ->and(array_column($navigation[0]['children'], 'href'))->toBe(['/erstes', '/aktiv']);
    });
});

describe('language switcher', function (): void {
    it('never redirects to another host named in the referer', function (): void {
        Language::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'en', 'name' => 'English']);

        Livewire::withHeaders(['referer' => 'https://site.test//evil.com'])
            ->test('website::frontend-language-switcher', ['tenantId' => $this->tenant->id])
            ->call('setLanguage', 'en')
            ->assertRedirect('/');
    });

    it('ignores a language the tenant does not offer', function (): void {
        session([WebsiteLanguage::SESSION_KEY => 'de']);

        Livewire::test('website::frontend-language-switcher', ['tenantId' => $this->tenant->id])
            ->call('setLanguage', 'xx');

        expect(session(WebsiteLanguage::SESSION_KEY))->toBe('de');
    });
});
