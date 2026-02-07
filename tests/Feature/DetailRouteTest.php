<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsLanguage;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

beforeEach(function (): void {
    $setup = $this->createUserWithCmsAccess();
    $this->user = $setup['user'];
    $this->tenant = $setup['tenant'];
    $this->actingAs($this->user);
});

it('loads page-detail via direct route', function (): void {
    $page = Page::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/page/' . $page->id)
        ->assertSuccessful()
        ->assertSeeLivewire('page-detail');
});

it('loads navigation-detail via direct route', function (): void {
    $navigation = Navigation::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/navigation/' . $navigation->id)
        ->assertSuccessful()
        ->assertSeeLivewire('navigation-detail');
});

it('loads global-parameter-detail via direct route', function (): void {
    $globalParameter = GlobalParameter::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/global-parameter/' . $globalParameter->id)
        ->assertSuccessful()
        ->assertSeeLivewire('global-parameter-detail');
});

it('loads collection-detail via direct route', function (): void {
    $collection = Collection::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/collection/' . $collection->id)
        ->assertSuccessful()
        ->assertSeeLivewire('collection-detail');
})->skip('collection-detail is file-based (stdClass), not model-based');

it('loads form-request-detail via direct route', function (): void {
    $formRequest = FormRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/form-request/' . $formRequest->id)
        ->assertSuccessful()
        ->assertSeeLivewire('form-request-detail');
})->skip('form-request-detail.yml config file does not exist yet');

it('loads form-type-detail via direct route', function (): void {
    $formType = FormType::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/form-type/' . $formType->id)
        ->assertSuccessful()
        ->assertSeeLivewire('form-type-detail');
});

it('loads cms-language-detail via direct route', function (): void {
    $cmsLanguage = CmsLanguage::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/language/' . $cmsLanguage->id)
        ->assertSuccessful()
        ->assertSeeLivewire('cms-language-detail');
});
