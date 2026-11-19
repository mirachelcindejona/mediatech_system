<?php

use App\Http\Controllers\Finance\CashFlowController;
use App\Http\Controllers\Finance\PettyCashController;
use App\Http\Controllers\Finance\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(["auth", "active"])->group(function () {

    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])
            ->middleware('permission:payments.read')
            ->name('index');

        Route::get('/create', [PaymentController::class, 'create'])
            ->middleware(['permission:payments.read', 'permission:payments.create'])
            ->name('create');

        Route::get('/export', [PaymentController::class, 'export'])
            ->middleware(['permission:payments.read', 'permission:payments.export'])
            ->name('export');

        Route::post('/', [PaymentController::class, 'store'])
            ->middleware('permission:payments.create')
            ->name('store');

        Route::post('/{payment}/validate', [PaymentController::class, 'validatePayment'])
            ->whereNumber('payment')
            ->middleware('permission:payments.approve')
            ->name('validate');

        Route::get('/{payment}/attachments/{attachment}', [PaymentController::class, 'showAttachment'])
            ->whereNumber('payment')
            ->whereNumber('attachment')
            ->middleware('permission:payments.read')
            ->name('attachments.show');

        Route::get('/{payment}', [PaymentController::class, 'show'])
            ->whereNumber('payment')
            ->middleware('permission:payments.read')
            ->name('show');
    });

    Route::prefix("cash-flow")->name("cash-flow.")->group(function () {
        Route::get("/", [CashFlowController::class, "index"])
            ->middleware("permission:cash_flow.read")
            ->name("index");

        Route::get("/export", [CashFlowController::class, "export"])
            ->middleware(["permission:cash_flow.read", "permission:cash_flow.export"])
            ->name("export");

        Route::get("/{cashTransaction}", [CashFlowController::class, "show"])
            ->middleware("permission:cash_flow.read")
            ->name("show");
    });

    Route::prefix("petty-cash")->name("petty-cash.")->group(function () {
        Route::get("/", [PettyCashController::class, "index"])
            ->middleware("permission:petty_cash.read")
            ->name("index");

        Route::get("/create", [PettyCashController::class, "create"])
            ->middleware("permission:petty_cash.create")
            ->name("create");

        Route::post("/", [PettyCashController::class, "store"])
            ->middleware("permission:petty_cash.create")
            ->name("store");

        Route::get("/trash", [PettyCashController::class, "trash"])
            ->middleware("permission:petty_cash.read")
            ->name("trash");

        Route::get("/export", [PettyCashController::class, "export"])
            ->middleware(["permission:petty_cash.read", "permission:petty_cash.export"])
            ->name("export");

        Route::get("/{id}", [PettyCashController::class, "show"])
            ->middleware("permission:petty_cash.read")
            ->name("show");

        Route::get("/{id}/edit", [PettyCashController::class, "edit"])
            ->middleware("permission:petty_cash.update")
            ->name("edit");

        Route::put("/{id}", [PettyCashController::class, "update"])
            ->middleware("permission:petty_cash.update")
            ->name("update");

        Route::delete("/{id}", [PettyCashController::class, "destroy"])
            ->middleware("permission:petty_cash.soft_delete")
            ->name("destroy");

        Route::post("/{id}/post", [PettyCashController::class, "post"])
            ->middleware(["permission:petty_cash.approve", "permission:petty_cash.post"])
            ->name("post");

        Route::post("/{id}/restore", [PettyCashController::class, "restore"])
            ->middleware("permission:petty_cash.restore")
            ->name("restore");

        Route::delete("/{id}/permanent", [PettyCashController::class, "permanentDelete"])
            ->middleware("permission:petty_cash.permanent_delete")
            ->name("permanent-delete");
    });
});
