<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;

uses(Tests\TestCase::class, RefreshDatabase::class, Noerd\Cms\Tests\Traits\CreatesCmsUser::class);

it('makes a future article visible once its publication date arrives', function (): void {
    ['tenant' => $tenant] = $this->createUserWithCmsAccess();

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
