<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\TaxController;

Route::middleware(['auth', 'verified', 'company.member'])->group(function () {
    Route::get('accounting/chart-of-accounts/suggested-code/{accountCategory}', [ChartOfAccountController::class, 'suggestedCode'])
        ->middleware('permission:accounting.account.manage')
        ->name('accounting.chart-of-accounts.suggested-code');

    Route::get('accounting/chart-of-accounts', [ChartOfAccountController::class, 'index'])
        ->middleware('permission:accounting.account.view')
        ->name('accounting.chart-of-accounts.index');

    Route::post('accounting/chart-of-accounts', [ChartOfAccountController::class, 'store'])
        ->middleware('permission:accounting.account.manage')
        ->name('accounting.chart-of-accounts.store');

    Route::put('accounting/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])
        ->middleware('permission:accounting.account.manage')
        ->name('accounting.chart-of-accounts.update');

    // Manajemen Pajak
    Route::middleware('permission:accounting.tax.view')->group(function () {
        Route::get('accounting/taxes/create', [TaxController::class, 'create'])->name('accounting.taxes.create');
        Route::get('accounting/taxes/{tax}/edit', [TaxController::class, 'edit'])->name('accounting.taxes.edit');
        Route::get('accounting/taxes/{tax}', [TaxController::class, 'show'])->name('accounting.taxes.show');
        Route::get('accounting/taxes', [TaxController::class, 'index'])->name('accounting.taxes.index');
    });

    Route::middleware('permission:accounting.tax.manage')->group(function () {
        Route::post('accounting/taxes', [TaxController::class, 'store'])->name('accounting.taxes.store');
        Route::put('accounting/taxes/{tax}', [TaxController::class, 'update'])->name('accounting.taxes.update');
        Route::delete('accounting/taxes/{tax}', [TaxController::class, 'destroy'])->name('accounting.taxes.destroy');
    });
});
