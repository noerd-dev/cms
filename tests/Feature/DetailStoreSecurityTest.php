<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Cms\Models\Article;
use Noerd\Cms\Models\Collection;
use Noerd\Cms\Models\CollectionDefinition;
use Noerd\Cms\Models\FormRequest;
use Noerd\Cms\Models\FormType;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Models\Navigation;
use Noerd\Cms\Models\Page;
use Noerd\Cms\Models\Redirect;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;
use Noerd\Enums\Profile;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\Tenant;

uses(Noerd\Cms\Tests\TestCase::class, RefreshDatabase::class);
uses(CreatesCmsUser::class);

/*
 | The custom store() methods of the CMS details: what the client sends beyond
 | the form never reaches the record, ids of another tenant never pass, and an
 | id that no longer resolves stores nothing instead of creating a record.
 */
beforeEach(function (): void {
    $this->actingAsCmsUser(Profile::Admin);
    $this->foreignTenant = Tenant::factory()->create();
});

describe('an id that no longer resolves creates no record', function (): void {
    it('in the article editor', function (): void {
        $article = Article::factory()->create(['tenant_id' => $this->tenantId]);
        $component = Livewire::withUrlParams(['articleId' => $article->id])->test('cms::cms-article-detail')
            ->set('detailData.title.en', 'Ghost');

        $article->delete();
        $component->call('store');

        expect(Article::query()->acrossTenants()->count())->toBe(0);
    });

    it('in the navigation editor', function (): void {
        $navigation = Navigation::factory()->create(['tenant_id' => $this->tenantId, 'navigation_key' => 'main']);
        $component = Livewire::withUrlParams(['navigationId' => $navigation->id])->test('cms::navigation-detail')
            ->set('detailData.name.en', 'Ghost');

        $navigation->delete();
        $component->call('store');

        expect(Navigation::query()->acrossTenants()->count())->toBe(0);
    });

    it('in the global parameter editor', function (): void {
        $parameter = GlobalParameter::factory()->create(['tenant_id' => $this->tenantId, 'key' => 'ghost']);
        $component = Livewire::withUrlParams(['globalParameterId' => $parameter->id])->test('cms::global-parameter-detail')
            ->set('detailData.value', 'Ghost');

        $parameter->delete();
        $component->call('store');

        expect(GlobalParameter::query()->acrossTenants()->count())->toBe(0);
    });

    it('in the redirect editor', function (): void {
        $target = Page::factory()->create(['tenant_id' => $this->tenantId]);
        $redirect = Redirect::factory()->create(['tenant_id' => $this->tenantId, 'source_path' => '/old', 'target_page_id' => $target->id]);
        $component = Livewire::withUrlParams(['redirectId' => $redirect->id])->test('cms::redirect-detail')
            ->set('detailData.source_path', '/older');

        $redirect->delete();
        $component->call('store');

        expect(Redirect::query()->acrossTenants()->count())->toBe(0);
    });
});

it('stores no id or tenant the client put into the article form', function (): void {
    $article = Article::factory()->create(['tenant_id' => $this->tenantId, 'title' => ['en' => 'Own']]);
    $foreign = Article::factory()->create(['tenant_id' => $this->foreignTenant->id, 'title' => ['en' => 'Foreign']]);

    Livewire::withUrlParams(['articleId' => $article->id])->test('cms::cms-article-detail')
        ->set('detailData.id', $foreign->id)
        ->set('detailData.tenant_id', $this->foreignTenant->id)
        ->set('detailData.title.en', 'Changed')
        ->call('store')
        ->assertHasNoErrors();

    expect($article->fresh()->title['en'])->toBe('Changed')
        ->and($article->fresh()->tenant_id)->toBe($this->tenantId)
        ->and(Article::query()->acrossTenants()->find($foreign->id)->title['en'])->toBe('Foreign');
});

describe('navigation', function (): void {
    it('refuses a parent, a page and a collection of another tenant', function (): void {
        $foreignParent = Navigation::factory()->create(['tenant_id' => $this->foreignTenant->id, 'navigation_key' => 'main']);
        $foreignPage = Page::factory()->create(['tenant_id' => $this->foreignTenant->id]);
        $foreignCollection = Collection::factory()->create(['tenant_id' => $this->foreignTenant->id]);

        Livewire::test('cms::navigation-detail')
            ->set('detailData.navigation_key', 'main')
            ->set('detailData.name.en', 'Item')
            ->set('detailData.parent_id', $foreignParent->id)
            ->set('detailData.page_id', $foreignPage->id)
            ->set('detailData.collection_id', $foreignCollection->id)
            ->call('store')
            ->assertHasErrors(['detailData.parent_id', 'detailData.page_id', 'detailData.collection_id']);

        expect(Navigation::query()->count())->toBe(0);
    });

    it('refuses a parent below the item itself', function (): void {
        $root = Navigation::factory()->create(['tenant_id' => $this->tenantId, 'navigation_key' => 'main', 'parent_id' => null]);
        $child = Navigation::factory()->create(['tenant_id' => $this->tenantId, 'navigation_key' => 'main', 'parent_id' => $root->id]);

        Livewire::withUrlParams(['navigationId' => $root->id])->test('cms::navigation-detail')
            ->set('detailData.parent_id', $child->id)
            ->call('store')
            ->assertHasErrors(['detailData.parent_id']);

        Livewire::withUrlParams(['navigationId' => $root->id])->test('cms::navigation-detail')
            ->set('detailData.parent_id', $root->id)
            ->call('store')
            ->assertHasErrors(['detailData.parent_id']);

        expect($root->fresh()->parent_id)->toBeNull();
    });

    it('stores a parent, page and collection of the own tenant', function (): void {
        $parent = Navigation::factory()->create(['tenant_id' => $this->tenantId, 'navigation_key' => 'main', 'parent_id' => null]);
        $page = Page::factory()->create(['tenant_id' => $this->tenantId]);

        Livewire::test('cms::navigation-detail')
            ->set('detailData.name.en', 'Child')
            ->set('detailData.parent_id', $parent->id)
            ->set('detailData.page_id', $page->id)
            ->call('store')
            ->assertHasNoErrors();

        $item = Navigation::query()->where('parent_id', $parent->id)->first();

        expect($item->page_id)->toBe($page->id)
            ->and($item->navigation_key)->toBe('MAIN');
    });
});

describe('form request', function (): void {
    function zzResendFixture(object $test): FormRequest
    {
        $formType = FormType::create([
            'tenant_id' => $test->tenantId,
            'key' => 'contact-form',
            'title' => 'Contact',
            'yml_path' => 'forms/contact.yml',
            'send_email' => true,
            'email_subject' => 'New request',
            'email_body' => 'A new request.',
            'notification_email' => 'admin@example.com',
        ]);

        return FormRequest::create([
            'tenant_id' => $test->tenantId,
            'form_type_id' => $formType->id,
            'form' => 'contact',
            'data' => ['name' => 'Test'],
        ]);
    }

    it('resends no notification for a user without the write permission', function (): void {
        Mail::fake();
        $this->actingAsCmsUser(Profile::ReadOnly);
        $formRequest = zzResendFixture($this);

        Livewire::test('cms::form-request-detail', ['modelId' => $formRequest->id])
            ->assertSet('canResendNotification', false)
            ->call('resendNotificationEmail');

        Mail::assertNothingSent();
    });

    it('reads the form type from the stored request, never from the client state', function (): void {
        Mail::fake();
        $formRequest = zzResendFixture($this);
        $formRequest->update(['form_type_id' => null]);
        $configured = FormType::query()->first();

        Livewire::test('cms::form-request-detail', ['modelId' => $formRequest->id])
            ->set('detailData.form_type_id', $configured->id)
            ->call('resendNotificationEmail');

        Mail::assertNothingSent();
    });
});

describe('collection definition', function (): void {
    beforeEach(function (): void {
        CollectionDefinition::create([
            'tenant_id' => $this->tenantId,
            'filename' => 'rename-test',
            'key' => 'RENAME_TEST',
            'title' => 'Rename Test',
            'title_list' => 'Rename Tests',
            'has_page' => false,
            'fields' => [
                ['name' => 'headline', 'label' => 'Headline', 'type' => 'text', 'colspan' => 6, 'options' => ['kept']],
            ],
        ]);

        $collection = Collection::create(['tenant_id' => $this->tenantId, 'collection_key' => 'RENAME_TEST', 'name' => 'Rename Tests']);
        $this->entry = Page::factory()->create(['tenant_id' => $this->tenantId, 'collection_id' => $collection->id, 'data' => ['headline' => 'Hello', 'secret' => 'keep']]);
    });

    it('keeps the rename state on the server', function (): void {
        $component = Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test']);

        expect(fn() => $component->set('pendingRenames', ['secret' => 'stolen']))
            ->toThrow(CannotUpdateLockedPropertyException::class)
            ->and(fn() => $component->set('showRenameConfirmation', true))
            ->toThrow(CannotUpdateLockedPropertyException::class)
            ->and(fn() => $component->set('originalFieldNames', ['secret']))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('renames nothing for a confirmation nobody was asked', function (): void {
        Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test'])
            ->dispatch('collectionRenameConfirmed');

        expect($this->entry->fresh()->data)->toBe(['headline' => 'Hello', 'secret' => 'keep']);
    });

    it('validates before it renames anything', function (): void {
        Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test'])
            ->set('fields.0.name', 'detailData.content_id')
            ->call('store')
            ->assertHasErrors(['fields.0.name'])
            ->assertSet('showRenameConfirmation', false);

        Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test'])
            ->set('fields.0.name', 'content_id')
            ->call('store')
            ->assertHasErrors(['fields.0.name']);

        expect($this->entry->fresh()->data)->toBe(['headline' => 'Hello', 'secret' => 'keep']);
    });

    it('renames nothing and saves nothing for an admin without the write permission', function (): void {
        // A finer permission layer (noerd-plus) denies writing definitions.
        Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?Authenticatable $user, ?string $model = null): bool => $model !== CollectionDefinition::class);

        $component = Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test'])
            ->set('fields.0.name', 'renamed')
            ->call('store')
            ->assertSet('showRenameConfirmation', false)
            ->call('confirmRenameAndSave');

        expect($this->entry->fresh()->data)->toBe(['headline' => 'Hello', 'secret' => 'keep'])
            ->and(CollectionDefinition::query()->where('filename', 'rename-test')->first()->fields[0]['name'])->toBe('headline');
    });

    it('derives the renames on the server and keeps the stored field settings', function (): void {
        Livewire::test('cms::collection-definition-detail', ['modelId' => 'rename-test'])
            ->set('fields.0.name', 'title')
            ->set('fields.0.options', ['injected'])
            ->call('store')
            ->assertSet('pendingRenames', ['headline' => 'title'])
            ->call('confirmRenameAndSave')
            ->assertHasNoErrors();

        $definition = CollectionDefinition::query()->where('filename', 'rename-test')->first();

        expect($this->entry->fresh()->data)->toBe(['secret' => 'keep', 'title' => 'Hello'])
            ->and($definition->fields[0]['name'])->toBe('title')
            ->and($definition->fields[0]['options'])->toBe(['kept']);
    });
});
