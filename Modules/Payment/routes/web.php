<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Payment\Http\Controllers\PaymentMethodController;
use Modules\Payment\Http\Controllers\PurchasePaymentController;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    // Master Cara Pembayaran (dipakai form Kirim Pembayaran).
    Route::get('payment-methods', [PaymentMethodController::class, 'index'])
        ->name('payment-methods.index');
    Route::post('payment-methods', [PaymentMethodController::class, 'store'])
        ->name('payment-methods.store');
    Route::patch('payment-methods/{method}', [PaymentMethodController::class, 'update'])
        ->name('payment-methods.update');

    // Kirim Pembayaran Pembelian Manual (entry dari detail faktur).
    Route::get('purchase-payments/new', [PurchasePaymentController::class, 'new'])
        ->name('purchase-payments.new');
    Route::post('purchase-payments', [PurchasePaymentController::class, 'store'])
        ->name('purchase-payments.store');
    Route::get('purchase-payments/{payment}', [PurchasePaymentController::class, 'show'])
        ->name('purchase-payments.show');
});
