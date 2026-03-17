<?php

use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('can create an author', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $author = Author::factory()->create(['tenant_id' => $tenant->id]);

    $this->assertDatabaseHas('authors', [
        'id' => $author->id,
        'tenant_id' => $tenant->id,
        'name' => $author->name,
    ]);
});

it('can update an author', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $author = Author::factory()->create(['tenant_id' => $tenant->id]);
    $author->update(['name' => 'Updated Name']);

    $this->assertDatabaseHas('authors', [
        'id' => $author->id,
        'name' => 'Updated Name',
    ]);
});

it('can delete an author', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $author = Author::factory()->create(['tenant_id' => $tenant->id]);
    $author->delete();

    $this->assertDatabaseMissing('authors', ['id' => $author->id]);
});

it('has articles relationship', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $author = Author::factory()->create(['tenant_id' => $tenant->id]);
    $article = Article::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $author->id,
    ]);

    expect($author->articles)->toHaveCount(1);
    expect($author->articles->first()->id)->toBe($article->id);
});

it('nullifies articles when author is deleted', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $author = Author::factory()->create(['tenant_id' => $tenant->id]);
    $article = Article::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $author->id,
    ]);

    $author->delete();

    $article->refresh();
    expect($article->author_id)->toBeNull();
});
