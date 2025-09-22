<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('cms')
    ->as('cms.')
    ->middleware(['web', 'auth', 'verified', 'cms'])
    ->group(function (): void {
        Volt::route('/', 'cms-dashboard')->name('dashboard');
        Volt::route('/pages', 'pages-list')->name('pages');
        Volt::route('/navigation', 'navigation-list')->name('navigation');
        Volt::route('global-parameters', 'global-parameters-list')->name('global-parameters');

        Volt::route('/collections', 'collection-entries-list')->name('collections');
        Volt::route('/collection-files', 'collections-list')->name('collection-files');
        Volt::route('/form-requests', 'form-requests-list')->name('form-requests');

        Volt::route('/settings', 'cms-settings-detail')->name('settings');
    });
