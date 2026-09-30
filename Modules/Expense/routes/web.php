<?php

use Illuminate\Support\Facades\Route;
use Modules\Expense\Http\Controllers\ExpenseController;
use Modules\Expense\Http\Controllers\ExpenseTagController;

Route::middleware(['auth', 'verified', 'company.member'])->group(function () {
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])
            ->name('index')
            ->middleware('permission:expense.view');

        Route::get('create', [ExpenseController::class, 'create'])
            ->name('create')
            ->middleware('permission:expense.create');

        Route::post('/', [ExpenseController::class, 'store'])
            ->name('store')
            ->middleware('permission:expense.create');

        Route::get('{expense}', [ExpenseController::class, 'show'])
            ->name('show')
            ->middleware('permission:expense.view');

        Route::post('tags', [ExpenseTagController::class, 'store'])
            ->name('tags.store')
            ->middleware('permission:expense.create');
    });
});
