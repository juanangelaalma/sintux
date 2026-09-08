<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Purchasing\Http\Controllers\GoodsReceiptController;
use Modules\Purchasing\Http\Controllers\JoinPurchaseInvoiceController;
use Modules\Purchasing\Http\Controllers\PurchaseInvoiceController;
use Modules\Purchasing\Http\Controllers\PurchaseOrderController;
use Modules\Purchasing\Http\Controllers\PurchaseQuoteController;
use Modules\Purchasing\Http\Controllers\PurchaseRequestController;
use Modules\Purchasing\Http\Controllers\PurchaseTagController;
use Modules\Purchasing\Http\Middleware\EnsureHeadquarters;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::prefix('purchasing')->name('purchasing.')->group(function () {
        // HQ-Only: seluruh modul purchasing hanya untuk Head Office.
        // Branch non-HQ memakai modul Transfer Stok (Warehouse).
        Route::middleware(EnsureHeadquarters::class)->group(function () {
            // Stock Requests
            Route::resource('requests', PurchaseRequestController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['requests' => 'request']);

            Route::post('requests/{request}/cancel', [PurchaseRequestController::class, 'cancel'])
                ->name('requests.cancel');

            // Purchase Invoices
            Route::resource('invoices', PurchaseInvoiceController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['invoices' => 'invoice']);

            // Join Purchase Invoices
            Route::resource('joins', JoinPurchaseInvoiceController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['joins' => 'join']);

            Route::post('joins/{join}/ready', [JoinPurchaseInvoiceController::class, 'ready'])
                ->name('joins.ready');

            // Purchase Quotes
            Route::resource('quotes', PurchaseQuoteController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['quotes' => 'quote']);

            Route::post('quotes/{quote}/send', [PurchaseQuoteController::class, 'send'])
                ->name('quotes.send');

            Route::post('quotes/{quote}/accept', [PurchaseQuoteController::class, 'accept'])
                ->name('quotes.accept');

            Route::post('quotes/{quote}/cancel', [PurchaseQuoteController::class, 'cancel'])
                ->name('quotes.cancel');

            // Purchase Orders
            Route::resource('orders', PurchaseOrderController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['orders' => 'order']);

            Route::post('orders/{order}/send', [PurchaseOrderController::class, 'send'])
                ->name('orders.send');

            Route::post('orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])
                ->name('orders.cancel');

            Route::post('tags', [PurchaseTagController::class, 'store'])
                ->name('tags.store');

            // Goods Receipts
            Route::resource('grns', GoodsReceiptController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['grns' => 'grn']);

            Route::post('grns/{grn}/post', [GoodsReceiptController::class, 'post'])
                ->name('grns.post');
        });
    });
});
