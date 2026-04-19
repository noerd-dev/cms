<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    // Create a second language for filter testing
    CmsLanguage::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'code' => 'en'],
        ['name' => 'English', 'is_active' => true, 'is_default' => false],
    );
});

it('can set listFilters without error', function (): void {
    Livewire::test('cms::pages-list')
        ->set('listFilters.language', 'de')
        ->assertHasNoErrors();
});

it('applies language filter without error', function (): void {
    $component = Livewire::test('cms::pages-list')
        ->set('listFilters.language', 'en');

    expect($component->get('listFilters')['language'])->toBe('en');
});
