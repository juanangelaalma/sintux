<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Purchasing\Http\Controllers\GoodsReceiptController;
use Modules\Purchasing\Http\Controllers\JoinPurchaseInvoiceController;
use Modules\Purchasing\Http\Controllers\PurchaseInvoiceController;
use Modules\Purchasing\Http\Controllers\PurchaseOrderController;
use Modules\Purchasing\Http\Controllers\PurchaseQuoteController;
use Modules\Purchasing\Http\Controllers\PurchaseRequestController;
use Modules\Purchasing\Http\Controllers\PurchaseReturnController;
use Modules\Purchasing\Http\Controllers\PurchaseTagController;
use Modules\Purchasing\Http\Middleware\EnsureHeadquarters;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::prefix('purchasing')->name('purchasing.')->group(function () {
        // GRN tunggal: fetch DO supplier + scan + submit cabang,
        // approval + posting + transfer oleh HO.
        Route::get('grns', [GoodsReceiptController::class, 'index'])
            ->name('grns.index');

        Route::get('grns/create', [GoodsReceiptController::class, 'create'])
            ->name('grns.create');

        Route::post('grns/fetch', [GoodsReceiptController::class, 'fetch'])
            ->name('grns.fetch');

        Route::post('grns', [GoodsReceiptController::class, 'store'])
            ->name('grns.store');

        Route::get('grns/{grn}', [GoodsReceiptController::class, 'show'])
            ->name('grns.show');

        Route::post('grns/{grn}/verify', [GoodsReceiptController::class, 'verify'])
            ->name('grns.verify');

        Route::post('grn-items/{item}/qty', [GoodsReceiptController::class, 'updateItemQty'])
            ->name('grn-items.qty');

        Route::post('grn-items/{item}/confirm', [GoodsReceiptController::class, 'confirmQty'])
            ->name('grn-items.confirm');

        Route::post('grns/{grn}/submit', [GoodsReceiptController::class, 'submit'])
            ->name('grns.submit');

        Route::post('grns/{grn}/revise', [GoodsReceiptController::class, 'revise'])
            ->name('grns.revise');

        // HQ-Only: seluruh modul purchasing hanya untuk Head Office.
        // Branch non-HQ memakai menu GRN + modul Transfer Stok (Warehouse).
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

            // Purchase Returns (tanpa index di v1, masuk via detail faktur)
            Route::get('returns/new', [PurchaseReturnController::class, 'new'])
                ->name('returns.new');
            Route::post('returns', [PurchaseReturnController::class, 'store'])
                ->name('returns.store');
            Route::get('returns/{return}', [PurchaseReturnController::class, 'show'])
                ->name('returns.show');
            Route::get('returns/{return}/attachments/{attachment}', [PurchaseReturnController::class, 'downloadAttachment'])
                ->name('returns.attachments.download');

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

            Route::get('orders/{order}/print', [PurchaseOrderController::class, 'print'])
                ->name('orders.print');

            Route::get('orders/{order}/download', [PurchaseOrderController::class, 'download'])
                ->name('orders.download');

            Route::post('orders/{order}/send', [PurchaseOrderController::class, 'send'])
                ->name('orders.send');

            Route::post('orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])
                ->name('orders.cancel');

            Route::post('tags', [PurchaseTagController::class, 'store'])
                ->name('tags.store');

            // Inbox approval GRN cabang.
            Route::get('grn-inbox', [GoodsReceiptController::class, 'inbox'])
                ->name('grn-inbox.index');

            Route::get('grn-inbox/{grn}', [GoodsReceiptController::class, 'showInbox'])
                ->name('grn-inbox.show');

            Route::post('grn-inbox/{grn}/approve', [GoodsReceiptController::class, 'approve'])
                ->name('grn-inbox.approve');

            Route::post('grn-inbox/{grn}/reject', [GoodsReceiptController::class, 'reject'])
                ->name('grn-inbox.reject');
        });
    });
});
