<?php

use Illuminate\Support\Facades\Route;

Route::prefix('cms')
    ->as('cms.')
    ->middleware(['noerd', 'app-access:cms'])
    ->group(function (): void {
        Route::livewire('/', 'cms::dashboard')->name('dashboard');
        Route::livewire('/pages', 'cms::pages-list')->name('pages');
        Route::livewire('/page/{modelId}', 'cms::page-detail')->name('page.detail');
        Route::livewire('/navigation', 'cms::navigation-list')->name('navigation');
        Route::livewire('/navigation/{modelId}', 'cms::navigation-detail')->name('navigation.detail');
        Route::livewire('global-parameters', 'cms::global-parameters-list')->name('global-parameters');
        Route::livewire('global-parameter/{modelId}', 'cms::global-parameter-detail')->name('global-parameter.detail');

        Route::livewire('/collections', 'cms::collection-entries-list')->name('collections');
        Route::livewire('/collection-definitions', 'cms::collection-definitions-list')->name('collection-definitions');
        Route::livewire('/collection-definition/{modelId}', 'cms::collection-definition-detail')->name('collection-definition.detail');
        Route::livewire('/form-requests', 'cms::form-requests-list')->name('form-requests');
        Route::livewire('/form-request/{modelId}', 'cms::form-request-page')->name('form-request.detail');
        Route::livewire('/form-types', 'cms::form-types-list')->name('form-types');
        Route::livewire('/form-type/{modelId}', 'cms::form-type-detail')->name('form-type.detail');

        Route::livewire('/authors', 'cms::authors-list')->name('authors');
        Route::livewire('/author/{modelId}', 'cms::author-detail')->name('author.detail');
        Route::livewire('/articles', 'cms::articles-list')->name('articles');
        Route::livewire('/article/{modelId}', 'cms::article-detail')->name('article.detail');

        Route::livewire('/settings', 'cms::settings-detail')->name('settings');
        Route::livewire('/languages', 'cms::languages-list')->name('languages');
        Route::livewire('/language/{modelId}', 'cms::language-detail')->name('language.detail');
    });
