<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Cms\Models\CmsLanguage;
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
        ->assertSeeLivewire('cms::page-detail');
});

it('loads navigation-detail via direct route', function (): void {
    $navigation = Navigation::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/navigation/' . $navigation->id)
        ->assertSuccessful()
        ->assertSeeLivewire('cms::navigation-detail');
});

it('loads global-parameter-detail via direct route', function (): void {
    $globalParameter = GlobalParameter::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/global-parameter/' . $globalParameter->id)
        ->assertSuccessful()
        ->assertSeeLivewire('cms::global-parameter-detail');
});

it('loads form-request-detail via direct route', function (): void {
    $formRequest = FormRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/form-request/' . $formRequest->id)
        ->assertSuccessful()
        ->assertSeeLivewire('cms::form-request-detail');
})->skip('form-request-detail.yml config file does not exist yet');

it('loads form-type-detail via direct route', function (): void {
    $formType = FormType::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/form-type/' . $formType->id)
        ->assertSuccessful()
        ->assertSeeLivewire('cms::form-type-detail');
});

it('loads language-detail via direct route', function (): void {
    $cmsLanguage = CmsLanguage::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/cms/language/' . $cmsLanguage->id)
        ->assertSuccessful()
        ->assertSeeLivewire('cms::language-detail');
});
