<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Noerd\Website\Controllers\ArticleController;
use Noerd\Website\Controllers\WebsiteController;
use Noerd\Website\Middleware\WebsiteMiddleware;

// The catch-all slug route is registered in WebsiteServiceProvider::registerCatchAllRoutes(),
// AFTER all other modules, so it never shadows another route.

Route::group(['middleware' => ['web', WebsiteMiddleware::class]], function (): void {
    Route::get('/index', [WebsiteController::class, 'index'])->name('website.index');
    Route::get('/blog', [ArticleController::class, 'index'])->name('website.articles');
    Route::get('/blog/{slug}', [ArticleController::class, 'show'])->name('website.article');
});
