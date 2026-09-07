<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);
});

describe('published scope', function (): void {
    it('filters published articles with scope', function (): void {
        $tenant = $this->tenant;

        // Published article (past date)
        $pastArticle = Article::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'publication_date' => now()->subDay(),
        ]);

        // Published article (today)
        $todayArticle = Article::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'publication_date' => now()->toDateString(),
        ]);

        // Future article
        Article::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'publication_date' => now()->addDay(),
        ]);

        // Inactive article
        Article::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
            'publication_date' => now()->subDay(),
        ]);

        // No publication date
        Article::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'publication_date' => null,
        ]);

        $published = Article::published()->where('tenant_id', $tenant->id)->get();

        expect($published->pluck('id')->sort()->values()->all())
            ->toBe(collect([$pastArticle->id, $todayArticle->id])->sort()->values()->all());
    });

    it('makes a future article visible once its publication date arrives', function (): void {
        $tenant = $this->tenant;

        $author = Author::create(['tenant_id' => $tenant->id, 'name' => 'Zz Author']);

        $article = Article::create([
            'tenant_id' => $tenant->id,
            'author_id' => $author->id,
            'title' => ['de' => 'Geplant'],
            'slug' => ['de' => '/geplant'],
            'is_active' => true,
            'publication_date' => now()->addDays(3)->toDateString(),
        ]);

        expect(Article::published()->pluck('id'))->not->toContain($article->id);

        $this->travelTo(now()->addDays(3)->startOfDay()->addHour());

        expect(Article::published()->pluck('id'))->toContain($article->id);

        $this->travelBack();
    });
});

describe('slug lookup', function (): void {
    it('finds article by slug', function (): void {
        $tenant = $this->tenant;

        $article = Article::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => ['de' => 'Mein Artikel'],
            'slug' => ['de' => '/mein-artikel'],
            'is_active' => true,
            'publication_date' => now()->subDay(),
        ]);

        $found = Article::published()
            ->where('tenant_id', $tenant->id)
            ->whereJsonContains('slug->de', '/mein-artikel')
            ->first();

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($article->id);
    });

    it('does not find unpublished article by slug', function (): void {
        $tenant = $this->tenant;

        Article::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => ['de' => 'Entwurf'],
            'slug' => ['de' => '/entwurf'],
            'is_active' => true,
            'publication_date' => now()->addWeek(),
        ]);

        $found = Article::published()
            ->where('tenant_id', $tenant->id)
            ->whereJsonContains('slug->de', '/entwurf')
            ->first();

        expect($found)->toBeNull();
    });
});

describe('author', function (): void {
    it('nullifies articles when author is deleted', function (): void {
        $tenant = $this->tenant;

        $author = Author::factory()->create(['tenant_id' => $tenant->id]);
        $article = Article::factory()->create([
            'tenant_id' => $tenant->id,
            'author_id' => $author->id,
        ]);

        $author->delete();

        $article->refresh();
        expect($article->author_id)->toBeNull();
    });
});
