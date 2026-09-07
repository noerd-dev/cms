<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->useOnlyLanguage('de');
    $this->addLanguage('en', 'English');
});

it('stores a new article for the current tenant', function (): void {
    Livewire::test('cms::article-detail')
        ->set('detailData', validDetailPayload(Article::class, ['tenant_id' => $this->tenantId]))
        ->set('detailData.title', ['de' => 'Neuigkeiten', 'en' => 'News'])
        ->call('store')
        ->assertHasNoErrors();

    $article = Article::where('tenant_id', $this->tenantId)->firstOrFail();
    expect($article->title['de'])->toBe('Neuigkeiten')
        ->and($article->tenant_id)->toBe($this->tenantId);
});

it('requires a title', function (): void {
    // The title rule is hard-coded in the component (translatable array), not YAML.
    Livewire::test('cms::article-detail')
        ->set('detailData.title', null)
        ->call('store')
        ->assertHasErrors(['detailData.title']);
});

it('derives a language-prefixed slug from the title once', function (): void {
    $component = Livewire::test('cms::article-detail')
        ->set('detailData.title.de', 'Über uns')
        ->set('detailData.title.en', 'About us');

    expect($component->get('detailData.slug.de'))->toBe('/ueber-uns')
        ->and($component->get('detailData.slug.en'))->toBe('/en/about-us');

    // A manually edited slug is never overwritten by a later title change.
    $component->set('detailData.slug.de', '/wir')->set('detailData.title.de', 'Team');
    expect($component->get('detailData.slug.de'))->toBe('/wir');
});

it('updates an existing article', function (): void {
    $article = Article::factory()->create(['tenant_id' => $this->tenantId]);

    Livewire::test('cms::article-detail', ['modelId' => $article->id])
        ->set('detailData.title.de', 'Geändert')
        ->call('store')
        ->assertHasNoErrors();

    expect($article->fresh()->title['de'])->toBe('Geändert');
});

it('cannot attach an author of another tenant', function (): void {
    $own = Author::factory()->create(['tenant_id' => $this->tenantId, 'name' => 'Own Author']);
    $foreign = Author::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Foreign Author']);

    $component = Livewire::test('cms::article-detail');

    $component->call('authorSelected', $foreign->id)
        ->assertSet('detailData.author_id', null);

    $component->call('authorSelected', $own->id)
        ->assertSet('detailData.author_id', $own->id)
        ->assertSet('relationTitles.author_id', 'Own Author');
});
