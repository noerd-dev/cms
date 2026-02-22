<?php

use Illuminate\Support\Facades\Route;

Route::prefix('cms')
    ->as('cms.')
    ->middleware(['web', 'auth', 'verified', 'app-access:cms'])
    ->group(function (): void {
        Route::livewire('/', 'cms-dashboard')->name('dashboard');
        Route::livewire('/pages', 'pages-list')->name('pages');
        Route::livewire('/page/{modelId}', 'page-detail')->name('page.detail');
        Route::livewire('/navigation', 'navigation-list')->name('navigation');
        Route::livewire('/navigation/{modelId}', 'navigation-detail')->name('navigation.detail');
        Route::livewire('global-parameters', 'global-parameters-list')->name('global-parameters');
        Route::livewire('global-parameter/{modelId}', 'global-parameter-detail')->name('global-parameter.detail');

        Route::livewire('/collections', 'collection-entries-list')->name('collections');
        Route::livewire('/form-requests', 'form-requests-list')->name('form-requests');
        Route::livewire('/form-request/{modelId}', 'form-request-detail')->name('form-request.detail');
        Route::livewire('/form-types', 'form-types-list')->name('form-types');
        Route::livewire('/form-type/{modelId}', 'form-type-detail')->name('form-type.detail');

        Route::livewire('/settings', 'cms-settings-detail')->name('settings');
        Route::livewire('/languages', 'cms-languages-list')->name('languages');
        Route::livewire('/language/{modelId}', 'cms-language-detail')->name('language.detail');
    });
