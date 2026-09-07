<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Author;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $this->actingAsCmsUser();
});

it('lists only the authors of the current tenant', function (): void {
    Author::factory()->create(['tenant_id' => $this->tenantId, 'name' => 'Own Author']);
    Author::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Foreign Author']);

    Livewire::test('cms::authors-list')
        ->assertSee('Own Author')
        ->assertDontSee('Foreign Author');
});

it('stores an author from a complete payload', function (): void {
    Livewire::test('cms::author-detail')
        ->set('detailData', validDetailPayload(Author::class, ['tenant_id' => $this->tenantId]))
        ->set('detailData.name', 'Jane Doe')
        ->call('store')
        ->assertHasNoErrors();

    expect(Author::where('tenant_id', $this->tenantId)->where('name', 'Jane Doe')->exists())->toBeTrue();
});

it('validates the required fields of the layout', function (): void {
    $component = Livewire::test('cms::author-detail')->set('detailData', [])->call('store');

    $component->assertHasErrors(requiredLayoutFields($component));
});

it('deletes an author', function (): void {
    $author = Author::factory()->create(['tenant_id' => $this->tenantId]);

    Livewire::test('cms::author-detail', ['modelId' => $author->id])->call('delete');

    expect(Author::find($author->id))->toBeNull();
});
