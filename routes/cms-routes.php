<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('cms')
    ->as('cms.')
    ->middleware(['web', 'auth', 'verified', 'app-access:cms'])
    ->group(function (): void {
        Volt::route('/', 'cms-dashboard')->name('dashboard');
        Volt::route('/pages', 'pages-list')->name('pages');
        Volt::route('/page/{model}', 'page-detail')->name('page.detail');
        Volt::route('/navigation', 'navigation-list')->name('navigation');
        Volt::route('/navigation/{model}', 'navigation-detail')->name('navigation.detail');
        Volt::route('global-parameters', 'global-parameters-list')->name('global-parameters');
        Volt::route('global-parameter/{model}', 'global-parameter-detail')->name('global-parameter.detail');

        Volt::route('/collections', 'collection-entries-list')->name('collections');
        Volt::route('/collection/{model}', 'collection-detail')->name('collection.detail');
        Volt::route('/collection-files', 'collections-list')->name('collection-files');
        Volt::route('/form-requests', 'form-requests-list')->name('form-requests');
        Volt::route('/form-request/{model}', 'form-request-detail')->name('form-request.detail');
        Volt::route('/form-types', 'form-types-list')->name('form-types');
        Volt::route('/form-type/{model}', 'form-type-detail')->name('form-type.detail');

        Volt::route('/settings', 'cms-settings-detail')->name('settings');
        Volt::route('/languages', 'cms-languages-list')->name('languages');
        Volt::route('/language/{model}', 'cms-language-detail')->name('language.detail');
    });
