<?php

use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('can create an article', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $article = Article::factory()->create(['tenant_id' => $tenant->id]);

    $this->assertDatabaseHas('articles', [
        'id' => $article->id,
        'tenant_id' => $tenant->id,
    ]);
});

it('can update an article', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $article = Article::factory()->create(['tenant_id' => $tenant->id]);
    $article->update(['body' => 'Updated body']);

    $this->assertDatabaseHas('articles', [
        'id' => $article->id,
        'body' => 'Updated body',
    ]);
});

it('can delete an article', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $article = Article::factory()->create(['tenant_id' => $tenant->id]);
    $article->delete();

    $this->assertDatabaseMissing('articles', ['id' => $article->id]);
});

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

it('casts title and slug as arrays', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $article = Article::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => ['de' => 'Titel', 'en' => 'Title'],
        'slug' => ['de' => '/titel', 'en' => '/en/title'],
    ]);

    $article->refresh();

    expect($article->title)->toBeArray();
    expect($article->title['de'])->toBe('Titel');
    expect($article->slug)->toBeArray();
    expect($article->slug['en'])->toBe('/en/title');
});

it('casts publication_date as date', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $article = Article::factory()->create([
        'tenant_id' => $tenant->id,
        'publication_date' => '2026-01-15',
    ]);

    $article->refresh();

    expect($article->publication_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($article->publication_date->format('Y-m-d'))->toBe('2026-01-15');
});

it('filters published articles with scope', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Published article
    Article::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'publication_date' => now()->subDay(),
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

    expect($published)->toHaveCount(1);
});

it('includes articles published today in scope', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Article::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'publication_date' => now()->toDateString(),
    ]);

    $published = Article::published()->where('tenant_id', $tenant->id)->get();

    expect($published)->toHaveCount(1);
});
