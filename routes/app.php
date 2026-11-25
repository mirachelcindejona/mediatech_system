<?php

use App\Http\Controllers\Admin\CmsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::view('/dashboard', 'admin.dashboard.index')
        ->middleware('permission:dashboard.read')->name('dashboard');

    // CMS Admin - proper route with admin layout
    Route::get('/cms', [App\Http\Controllers\Admin\CmsController::class, 'index'])
        ->middleware('permission:cms.read')->name('cms.pages.index');
    Route::get('/cms/preview-draft', [CmsController::class, 'preview'])
        ->middleware('permission:cms.read')->name('cms.pages.preview');
    Route::get('/cms/pages', [App\Http\Controllers\Admin\CmsController::class, 'index'])
        ->middleware('permission:cms.read')->name('cms.pages.list');
    Route::get('/cms/partners', [App\Http\Controllers\Admin\CmsController::class, 'partners'])
        ->middleware('permission:cms.read')->name('cms.partners.index');
    Route::get('/cms/media', [App\Http\Controllers\Admin\CmsController::class, 'media'])
        ->middleware('permission:cms.read')->name('cms.media.index');
    Route::get('/cms/trash', [App\Http\Controllers\Admin\CmsController::class, 'trash'])
        ->middleware('permission:cms.read')->name('cms.trash.index');

    // Aksi Sampah CMS: berada dalam middleware auth + active dari group ini.
    // cms.update merupakan permission tulis CMS yang sudah digunakan di bawah.
    Route::post('/cms/trash/{type}/{id}/restore', [CmsController::class, 'restoreTrash'])
        ->where('type', 'cms_pages|cms_items|cms_partners|cms_media|cms_fields')
        ->whereNumber('id')
        ->middleware('permission:cms.update')
        ->name('cms.trash.restore');

    Route::delete('/cms/trash/{type}/{id}', [CmsController::class, 'forceDeleteTrash'])
        ->where('type', 'cms_pages|cms_items|cms_partners|cms_media|cms_fields')
        ->whereNumber('id')
        ->middleware('permission:cms.update')
        ->name('cms.trash.force-delete');

    // Simpan konten halaman CMS (Simpan draft / Publish)
    Route::put('/cms/{slug}', [CmsController::class, 'update'])
        ->where('slug', '[a-z0-9\-]+')
        ->middleware('permission:cms.update')->name('cms.pages.update');

    require __DIR__ . '/admin.php';
    require __DIR__ . '/finance.php';
});

// Temporary public route for testing CMS without login
Route::get('/cms-test', function () {
    return redirect('/cms.html');
});