<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Services\PageSlugService;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
    $this->slugs = app(PageSlugService::class);
});

it('folds umlauts and prefixes every language but the default one', function (): void {
    expect($this->slugs->generate('Über uns & Team', 'de', 'de'))->toBe('/ueber-uns-team')
        ->and($this->slugs->generate('Über uns', 'en', 'de'))->toBe('/en/ueber-uns')
        ->and($this->slugs->generate('Startseite', null, 'de'))->toBe('/startseite');
});

it('counts up until the slug is unique within the tenant and language', function (): void {
    Page::factory()->create(['tenant_id' => $this->tenantId, 'slug' => ['en' => '/team']]);
    Page::factory()->create(['tenant_id' => $this->tenantId, 'slug' => ['en' => '/team-2']]);

    expect($this->slugs->ensureUnique('/team', 'en', $this->tenantId))->toBe('/team-3');
});

it('ignores pages of other tenants and the record itself', function (): void {
    $foreign = Tenant::factory()->create();
    Page::factory()->create(['tenant_id' => $foreign->id, 'slug' => ['en' => '/team']]);
    $own = Page::factory()->create(['tenant_id' => $this->tenantId, 'slug' => ['en' => '/team']]);

    expect($this->slugs->ensureUnique('/team', 'en', $this->tenantId, excludeId: $own->id))->toBe('/team')
        ->and($this->slugs->ensureUnique('/team', 'en', $this->tenantId))->toBe('/team-2');
});

it('rejects a language code that is not configured anywhere', function (): void {
    // The code ends up in a JSON path — only known codes may reach the query.
    expect(fn() => $this->slugs->ensureUnique('/team', 'x"y', $this->tenantId))
        ->toThrow(InvalidArgumentException::class);
});
