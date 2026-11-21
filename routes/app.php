<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::view('/dashboard', 'admin.dashboard.index')
        ->middleware('permission:dashboard.read')->name('dashboard');

    // CMS Admin - proper route with admin layout
    Route::get('/cms', [App\Http\Controllers\Admin\CmsController::class, 'index'])
        ->middleware('permission:cms.read')->name('cms.pages.index');
    Route::get('/cms/pages', [App\Http\Controllers\Admin\CmsController::class, 'index'])
        ->middleware('permission:cms.read')->name('cms.pages.index');
    Route::get('/cms/partners', [App\Http\Controllers\Admin\CmsController::class, 'partners'])
        ->middleware('permission:cms.read')->name('cms.partners.index');
    Route::get('/cms/media', [App\Http\Controllers\Admin\CmsController::class, 'media'])
        ->middleware('permission:cms.read')->name('cms.media.index');
    Route::get('/cms/trash', [App\Http\Controllers\Admin\CmsController::class, 'trash'])
        ->middleware('permission:cms.read')->name('cms.trash.index');

    require __DIR__ . '/admin.php';
    require __DIR__ . '/finance.php';
});

// Temporary public route for testing CMS without login
Route::get('/cms-test', function () {
    return redirect('/cms.html');
});

Route::put('/cms/{slug}', [CmsController::class, 'update'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('cms.pages.update');