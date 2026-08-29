<?php

use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('belongs to an author', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $author = Author::factory()->create(['tenant_id' => $tenant->id]);
    $article = Article::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $author->id,
    ]);

    expect($article->author->id)->toBe($author->id);
});

it('filters published articles with scope', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

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
