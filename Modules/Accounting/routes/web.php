<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;

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
});
