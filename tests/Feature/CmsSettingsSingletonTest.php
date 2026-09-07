<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\CmsSetting;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Enums\Profile;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

it('keeps exactly one settings row per tenant', function (): void {
    $this->actingAsCmsUser(Profile::Admin);

    CmsSetting::create(['tenant_id' => $this->tenantId]);

    expect(fn() => CmsSetting::create(['tenant_id' => $this->tenantId]))->toThrow(QueryException::class);

    // The settings page updates the singleton instead of inserting a second row.
    Livewire::test('cms::settings-page')->set('detailData.google_analytics_id', 'G-1')->call('store');
    Livewire::test('cms::settings-page')->set('detailData.google_analytics_id', 'G-2')->call('store');

    expect(CmsSetting::where('tenant_id', $this->tenantId)->count())->toBe(1)
        ->and(CmsSetting::where('tenant_id', $this->tenantId)->value('google_analytics_id'))->toBe('G-2');
});

it('allows a global parameter key once per tenant and reports a duplicate as a validation error', function (): void {
    $this->actingAsCmsUser();

    GlobalParameter::factory()->create(['tenant_id' => $this->tenantId, 'key' => 'footer_text']);

    expect(fn() => GlobalParameter::factory()->create(['tenant_id' => $this->tenantId, 'key' => 'footer_text']))
        ->toThrow(QueryException::class);

    Livewire::test('cms::global-parameter-detail')
        ->set('detailData.key', 'footer_text')
        ->set('detailData.value', 'Duplicate')
        ->call('store')
        ->assertHasErrors(['detailData.key']);

    expect(GlobalParameter::where('tenant_id', $this->tenantId)->where('key', 'footer_text')->count())->toBe(1);
});
