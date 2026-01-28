<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    session()->forget(['activeListFilters', 'selectedLanguage']);

    ['user' => $this->user, 'tenant' => $this->tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($this->user);

    // Create a second language for filter testing
    CmsLanguage::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'code' => 'en'],
        ['name' => 'English', 'is_active' => true, 'is_default' => false],
    );
});

it('can set activeListFilters without error', function (): void {
    Volt::test('collection-entries-list', ['collectionKey' => 'contacts'])
        ->set('activeListFilters.language', 'de')
        ->assertHasNoErrors();
});

it('applies language filter without error', function (): void {
    $component = Volt::test('collection-entries-list', ['collectionKey' => 'contacts'])
        ->set('activeListFilters.language', 'en');

    expect($component->get('activeListFilters')['language'])->toBe('en');
});
