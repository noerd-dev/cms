<?php

use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('shows published articles on blog page', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $author = Author::factory()->create(['tenant_id' => $tenant->id]);

    Article::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $author->id,
        'title' => ['de' => 'Veröffentlichter Artikel'],
        'slug' => ['de' => '/veroeffentlichter-artikel'],
        'is_active' => true,
        'publication_date' => now()->subDay(),
    ]);

    Article::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => ['de' => 'Zukunfts Artikel'],
        'slug' => ['de' => '/zukunfts-artikel'],
        'is_active' => true,
        'publication_date' => now()->addDay(),
    ]);

    expect(Article::published()->where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(Article::published()->where('tenant_id', $tenant->id)->first()->title['de'])->toBe('Veröffentlichter Artikel');
});

it('hides inactive articles', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Article::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => ['de' => 'Inaktiver Artikel'],
        'slug' => ['de' => '/inaktiver-artikel'],
        'is_active' => false,
        'publication_date' => now()->subDay(),
    ]);

    expect(Article::published()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('finds article by slug', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

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
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

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
