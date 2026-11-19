<?php

use App\Http\Controllers\Inventory\GoodsIssueController;
use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:inventory.read')->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', InventoryController::class)->name('index');
    Route::get('/stocks', [InventoryController::class, 'stocks'])->name('stocks');
    Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
    Route::get('/orders', [InventoryController::class, 'orders'])->middleware('permission:inventory.update')->name('orders');
    Route::get('/orders/{salesOrder}', [InventoryController::class, 'order'])->whereNumber('salesOrder')->middleware('permission:inventory.update')->name('orders.show');
    Route::get('/export/{table}', [InventoryController::class, 'export'])->whereIn('table', ['stocks', 'movements'])->middleware('permission:inventory.export')->name('export');
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])->name('goods-receipts.store');
    Route::post('/goods-issues', [GoodsIssueController::class, 'store'])->name('goods-issues.store');
});
