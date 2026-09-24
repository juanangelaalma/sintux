<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Payment\Http\Controllers\PaymentMethodController;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    // Master Cara Pembayaran (dipakai form Kirim Pembayaran).
    Route::get('payment-methods', [PaymentMethodController::class, 'index'])
        ->name('payment-methods.index');
    Route::post('payment-methods', [PaymentMethodController::class, 'store'])
        ->name('payment-methods.store');
    Route::patch('payment-methods/{method}', [PaymentMethodController::class, 'update'])
        ->name('payment-methods.update');
});
