<?php

use App\Http\Controllers\Sales\InvoiceController;
use App\Http\Controllers\Sales\InvoicePrintController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])
            ->middleware('permission:invoices.read')
            ->name('index');

        Route::get('/create', [InvoiceController::class, 'create'])
            ->middleware('permission:invoices.create')
            ->name('create');

        Route::post('/', [InvoiceController::class, 'store'])
            ->middleware('permission:invoices.create')
            ->name('store');

        Route::get('/{invoice}/print', InvoicePrintController::class)
            ->whereNumber('invoice')
            ->middleware('permission:invoices.read')
            ->name('print');

        Route::get('/export', [InvoiceController::class, 'export'])
            ->middleware(['permission:invoices.read', 'permission:invoices.export'])
            ->name('export');

        Route::get('/{invoice}', [InvoiceController::class, 'show'])
            ->whereNumber('invoice')
            ->middleware('permission:invoices.read')
            ->name('show');
    });
});
