<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('cms')
    ->as('cms.')
    ->middleware(['web', 'auth', 'verified', 'cms'])
    ->group(function (): void {
        Volt::route('/', 'cms-dashboard')->name('dashboard');
        Volt::route('/pages', 'pages-table')->name('pages');
        Volt::route('/navigation', 'navigation-table')->name('navigation');
        Volt::route('global-parameters', 'global-parameters-table')->name('global-parameters');

        Volt::route('/collections', 'collection-entries-table')->name('collections');
        Volt::route('/collection-files', 'collections-table')->name('collection-files');
        Volt::route('/form-requests', 'form-requests-table')->name('form-requests');

        Volt::route('/settings', 'cms-settings-component')->name('settings');
    });
