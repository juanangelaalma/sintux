<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Purchasing\Http\Controllers\GoodsReceiptController;
use Modules\Purchasing\Http\Controllers\JoinPurchaseInvoiceController;
use Modules\Purchasing\Http\Controllers\PurchaseInvoiceController;
use Modules\Purchasing\Http\Controllers\PurchaseOrderController;
use Modules\Purchasing\Http\Controllers\PurchaseQuoteController;
use Modules\Purchasing\Http\Controllers\PurchaseRequestController;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::prefix('purchasing')->name('purchasing.')->group(function () {
        Route::resource('requests', PurchaseRequestController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['requests' => 'request']);

        Route::post('requests/{request}/approve', [PurchaseRequestController::class, 'approve'])
            ->name('requests.approve');

        Route::post('requests/{request}/cancel', [PurchaseRequestController::class, 'cancel'])
            ->name('requests.cancel');

        Route::resource('quotes', PurchaseQuoteController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['quotes' => 'quote']);

        Route::post('quotes/{quote}/send', [PurchaseQuoteController::class, 'send'])
            ->name('quotes.send');

        Route::post('quotes/{quote}/accept', [PurchaseQuoteController::class, 'accept'])
            ->name('quotes.accept');

        Route::post('quotes/{quote}/cancel', [PurchaseQuoteController::class, 'cancel'])
            ->name('quotes.cancel');

        Route::resource('orders', PurchaseOrderController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['orders' => 'order']);

        Route::post('orders/{order}/approve', [PurchaseOrderController::class, 'approve'])
            ->name('orders.approve');

        Route::post('orders/{order}/send', [PurchaseOrderController::class, 'send'])
            ->name('orders.send');

        Route::post('orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('orders.cancel');

        Route::resource('grns', GoodsReceiptController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['grns' => 'grn']);

        Route::post('grns/{grn}/post', [GoodsReceiptController::class, 'post'])
            ->name('grns.post');

        Route::resource('invoices', PurchaseInvoiceController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['invoices' => 'invoice']);

        Route::post('invoices/{invoice}/approve', [PurchaseInvoiceController::class, 'approve'])
            ->name('invoices.approve');

        Route::resource('joins', JoinPurchaseInvoiceController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['joins' => 'join']);

        Route::post('joins/{join}/ready', [JoinPurchaseInvoiceController::class, 'ready'])
            ->name('joins.ready');
    });
});
