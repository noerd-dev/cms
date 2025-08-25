<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('cms')
    ->as('cms.')
    ->middleware(['web', 'auth', 'verified', 'cms'])
    ->group(function (): void {
        Volt::route('/pages', 'pages-table')->name('pages');
        Volt::route('/navigation', 'navigation-table')->name('navigation');
        Volt::route('global-parameters', 'global-parameters-table')->name('global-parameters');

        Volt::route('/collections', 'collections-table')->name('collections');
        Volt::route('/form-requests', 'form-requests-table')->name('form-requests');

        // CMS Settings page (homepage selection)
        Volt::route('/settings', 'cms-settings-component')->name('settings');

        Route::get('test', function (): void {
            dd(auth('web')->user());
        })->name('dashboard');
    });
