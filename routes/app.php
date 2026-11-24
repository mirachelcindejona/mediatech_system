<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::view('/dashboard', 'admin.dashboard.index')
        ->middleware('permission:dashboard.read')->name('dashboard');

    Route::view('/sales-orders', 'sales.sales-orders.index')
        ->name('sales-orders.index');

    require __DIR__ . '/admin.php';
    require __DIR__ . '/finance.php';
});
