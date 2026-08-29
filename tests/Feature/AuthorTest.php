<?php

use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

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
