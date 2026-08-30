<?php

use Illuminate\Support\Facades\Route;
use Noerd\Cms\Http\Controllers\FormRequestController;

// The throttle is declared here on purpose: the host's `api` group carries no
// rate limiter by default, and a token-authenticated public endpoint must not
// depend on host configuration for abuse protection.
Route::prefix('api/cms')
    ->as('api.cms.')
    ->middleware(['api', 'throttle:60,1', 'cms_api'])
    ->group(function (): void {
        Route::post('/form-requests', [FormRequestController::class, 'store'])->name('form-requests.store');
    });
