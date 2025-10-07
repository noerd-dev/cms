<?php

use Illuminate\Support\Facades\Route;
use Noerd\Website\Controllers\WebsiteController;
use Noerd\Website\Middleware\WebsiteMiddleware;

// Routes are now registered in WebsiteServiceProvider::registerCatchAllRoutes()
// This ensures they are loaded AFTER all other modules to maintain proper priority

Route::group(['middleware' => ['web', WebsiteMiddleware::class]], function (): void {
    Route::get('/index', [WebsiteController::class, 'index'])->name('website.index');
    Route::get('/page/{pageId}', [WebsiteController::class, 'page'])->name('website.page');
});
