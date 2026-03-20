<?php

use Noerd\Cms\Models\ElementPage;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('can add a repeater item', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $tenant->id,
    ]);

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => 'detail_cards',
        'data' => json_encode(['headline' => ['de' => '', 'en' => ''], 'items' => []]),
        'sort' => 1,
    ]);

    Livewire::test('element-page-detail', ['modelId' => $elementPage->id])
        ->call('addRepeaterItem', 'items')
        ->assertSet('model.items.0.image', '')
        ->assertSet('model.items.0.name', ['de' => '', 'en' => ''])
        ->assertHasNoErrors();
});

it('can remove a repeater item', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $tenant->id,
    ]);

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => 'detail_cards',
        'data' => json_encode([
            'headline' => ['de' => '', 'en' => ''],
            'items' => [
                ['image' => '', 'name' => ['de' => 'A', 'en' => ''], 'subheader' => ['de' => '', 'en' => ''], 'text' => ['de' => '', 'en' => '']],
                ['image' => '', 'name' => ['de' => 'B', 'en' => ''], 'subheader' => ['de' => '', 'en' => ''], 'text' => ['de' => '', 'en' => '']],
            ],
        ]),
        'sort' => 1,
    ]);

    Livewire::test('element-page-detail', ['modelId' => $elementPage->id])
        ->call('removeRepeaterItem', 'items', 0)
        ->assertSet('model.items.0.name.de', 'B')
        ->assertHasNoErrors();
});

it('can reorder repeater items', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $tenant->id,
    ]);

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => 'detail_cards',
        'data' => json_encode([
            'headline' => ['de' => '', 'en' => ''],
            'items' => [
                ['image' => '', 'name' => ['de' => 'First', 'en' => ''], 'subheader' => ['de' => '', 'en' => ''], 'text' => ['de' => '', 'en' => '']],
                ['image' => '', 'name' => ['de' => 'Second', 'en' => ''], 'subheader' => ['de' => '', 'en' => ''], 'text' => ['de' => '', 'en' => '']],
            ],
        ]),
        'sort' => 1,
    ]);

    Livewire::test('element-page-detail', ['modelId' => $elementPage->id])
        ->call('reorderRepeaterItem', 'items', 0, 1)
        ->assertSet('model.items.0.name.de', 'Second')
        ->assertSet('model.items.1.name.de', 'First')
        ->assertHasNoErrors();
});

it('stores repeater data correctly', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $page = Page::create([
        'name' => json_encode(['en' => 'Test Page']),
        'slug' => json_encode(['en' => 'test-page']),
        'tenant_id' => $tenant->id,
    ]);

    $elementPage = ElementPage::create([
        'page_id' => $page->id,
        'element_key' => 'detail_cards',
        'data' => json_encode(['headline' => ['de' => '', 'en' => ''], 'items' => []]),
        'sort' => 1,
    ]);

    Livewire::test('element-page-detail', ['modelId' => $elementPage->id])
        ->call('addRepeaterItem', 'items')
        ->set('model.items.0.name.de', 'Test Karte')
        ->call('store')
        ->assertHasNoErrors();

    $elementPage->refresh();
    $data = json_decode($elementPage->data, true);
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['name']['de'])->toBe('Test Karte');
});
