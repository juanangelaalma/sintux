<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Warehouse\Http\Controllers\StockAdjustmentController;
use Modules\Warehouse\Http\Controllers\StockBalanceController;
use Modules\Warehouse\Http\Controllers\StockMovementController;
use Modules\Warehouse\Http\Controllers\StockRequestController;
use Modules\Warehouse\Http\Controllers\StockTransferController;
use Modules\Warehouse\Http\Controllers\WarehouseController;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        Route::resource('warehouses', WarehouseController::class)
            ->parameters(['warehouses' => 'warehouse']);

        Route::get('stock-balances', [StockBalanceController::class, 'index'])
            ->name('stock-balances.index');

        Route::get('stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements.index');

        Route::get('stock-layers/{warehouse}/{product_variant}', [StockMovementController::class, 'layers'])
            ->name('stock-layers.index');

        Route::resource('stock-requests', StockRequestController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['stock-requests' => 'stock_request']);

        Route::post('stock-requests/{stock_request}/approve', [StockRequestController::class, 'approve'])
            ->name('stock-requests.approve');

        Route::resource('stock-transfers', StockTransferController::class)
            ->only(['index', 'show'])
            ->parameters(['stock-transfers' => 'stock_transfer']);

        Route::post('stock-transfers/{stock_transfer}/ship', [StockTransferController::class, 'ship'])
            ->name('stock-transfers.ship');

        Route::post('stock-transfers/{stock_transfer}/receive', [StockTransferController::class, 'receive'])
            ->name('stock-transfers.receive');

        Route::resource('adjustments', StockAdjustmentController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['adjustments' => 'adjustment']);

        Route::post('adjustments/{adjustment}/post', [StockAdjustmentController::class, 'post'])
            ->name('adjustments.post');
    });
});
