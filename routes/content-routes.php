<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Livewire\ContentComponent;

Route::group(['middleware' => ['web', 'auth', 'verified']], function (): void {
    Route::get('contents', ContentComponent::class)->name('contents');
});

Route::prefix('cms')
    ->as('cms.')
    ->middleware(['web', 'auth', 'verified', 'cms'])
    ->group(function (): void {
        Route::get('dashboard', ContentComponent::class)->name('dashboard');
        Volt::route('/pages', 'pages-table')->name('pages');
        Volt::route('global-parameters', 'global-parameters-table')->name('global-parameters');

        Volt::route('/collections', 'collections-table')->name('collections');

        Route::get('test', function (): void {
            dd(auth('web')->user());
        })->name('dashboard');
    });
