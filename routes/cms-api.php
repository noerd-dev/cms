<?php

use Illuminate\Support\Facades\Route;
use Noerd\Cms\Http\Controllers\FormRequestController;

Route::prefix('api/cms')
    ->as('api.cms.')
    ->middleware(['api', 'cms_api'])
    ->group(function (): void {
        Route::post('/form-requests', [FormRequestController::class, 'store'])->name('form-requests.store');
    });


