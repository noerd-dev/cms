<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

it('counts only the current tenant\'s pages and navigation entries', function (): void {
    $this->actingAsCmsUser();

    Page::factory()->count(2)->create(['tenant_id' => $this->tenantId]);
    Navigation::factory()->create(['tenant_id' => $this->tenantId]);

    $foreign = Tenant::factory()->create();
    Page::factory()->count(5)->create(['tenant_id' => $foreign->id]);
    Navigation::factory()->count(3)->create(['tenant_id' => $foreign->id]);

    Livewire::test('cms::dashboard')
        ->assertViewHas('pagesCount', 2)
        ->assertViewHas('navigationCount', 1);
});
