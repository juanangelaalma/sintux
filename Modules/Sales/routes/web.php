<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Sales\Http\Controllers\SalesInvoiceController;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::prefix('sales')->name('sales.')->group(function () {
        // Per-branch: setiap cabang menjual dari gudangnya sendiri.
        Route::resource('invoices', SalesInvoiceController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['invoices' => 'invoice']);
    });
});
