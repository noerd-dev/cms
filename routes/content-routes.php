<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('cms')
    ->as('cms.')
    ->middleware(['web', 'auth', 'verified', 'cms'])
    ->group(function (): void {
        Volt::route('/pages', 'pages-table')->name('pages');
        Volt::route('global-parameters', 'global-parameters-table')->name('global-parameters');

        Volt::route('/collections', 'collections-table')->name('collections');
        Volt::route('/form-requests', 'form-requests-table')->name('form-requests');

        Route::get('test', function (): void {
            dd(auth('web')->user());
        })->name('dashboard');
    });
