<?php

declare(strict_types=1);

namespace Noerd\Website\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Models\NoerdSettings;
use Noerd\Website\Models\Tenant;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->author = Author::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function zzWebsiteArticle(object $test, array $attributes = []): Article
{
    return Article::factory()->create(array_merge([
        'tenant_id' => $test->tenant->id,
        'author_id' => $test->author->id,
        'is_active' => true,
        'publication_date' => now()->subDay(),
    ], $attributes));
}

describe('/blog', function (): void {
    it('lists only the published articles of the requested tenant', function (): void {
        $published = zzWebsiteArticle($this, [
            'title' => ['de' => 'Veroeffentlicht'],
            'slug' => ['de' => '/veroeffentlicht'],
        ]);

        $draft = zzWebsiteArticle($this, [
            'title' => ['de' => 'Entwurf'],
            'slug' => ['de' => '/entwurf'],
            'is_active' => false,
        ]);

        $future = zzWebsiteArticle($this, [
            'title' => ['de' => 'Geplant'],
            'slug' => ['de' => '/geplant'],
            'publication_date' => now()->addWeek(),
        ]);

        $otherTenant = Tenant::factory()->create();
        $foreign = Article::factory()->create([
            'tenant_id' => $otherTenant->id,
            'title' => ['de' => 'Fremd'],
            'slug' => ['de' => '/fremd'],
            'is_active' => true,
            'publication_date' => now()->subDay(),
        ]);

        $response = $this->get('/blog?uuid=' . $this->tenant->uuid);

        $response->assertOk();
        $response->assertViewIs('website::articles');

        expect($response->viewData('articles')->pluck('id')->all())->toBe([$published->id])
            ->and($response->viewData('articles')->pluck('id')->all())
            ->not->toContain($draft->id, $future->id, $foreign->id);
    });
});

describe('/blog/{slug}', function (): void {
    it('renders a published article by its slug', function (): void {
        $article = zzWebsiteArticle($this, [
            'title' => ['de' => 'Mein Artikel'],
            'slug' => ['de' => '/mein-artikel'],
        ]);

        $response = $this->get('/blog/mein-artikel?uuid=' . $this->tenant->uuid);

        $response->assertOk();
        $response->assertViewIs('website::article');
        expect($response->viewData('article')->id)->toBe($article->id);
    });

    it('writes the publication date in the tenant locale', function (): void {
        // No noerd user is logged in on the public site: the article's tenant
        // decides the date format, not a hard-coded German pattern.
        NoerdSettings::create(['tenant_id' => $this->tenant->id, 'currency' => 'USD', 'locale' => 'en-US']);

        zzWebsiteArticle($this, [
            'title' => ['de' => 'Datum'],
            'slug' => ['de' => '/datum'],
            'publication_date' => '2026-03-09',
        ]);

        $this->get('/blog/datum?uuid=' . $this->tenant->uuid)
            ->assertOk()
            ->assertSee('03/09/2026')
            ->assertDontSee('09.03.2026');

        $this->get('/blog?uuid=' . $this->tenant->uuid)
            ->assertOk()
            ->assertSee('03/09/2026')
            ->assertDontSee('09.03.2026');
    });

    it('returns 404 for an article that is not published for this tenant', function (array $attributes, bool $foreignTenant): void {
        if ($foreignTenant) {
            $attributes['tenant_id'] = Tenant::factory()->create()->id;
            $attributes['author_id'] = null;
        }

        zzWebsiteArticle($this, array_merge([
            'title' => ['de' => 'Nicht sichtbar'],
            'slug' => ['de' => '/nicht-sichtbar'],
        ], $attributes));

        $this->get('/blog/nicht-sichtbar?uuid=' . $this->tenant->uuid)->assertNotFound();
    })->with([
        'a draft' => [['is_active' => false], false],
        'a future publication date' => [['publication_date' => now()->addWeek()], false],
        'another tenant\'s article' => [[], true],
    ]);
});
