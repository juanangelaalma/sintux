<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Contact\Http\Controllers\ContactController;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::get('company/contacts/{type}/create', [ContactController::class, 'create'])->name('company.contacts.create');
    Route::get('company/contacts/{type}', [ContactController::class, 'index'])->name('company.contacts.index');
    Route::post('company/contacts/{type}', [ContactController::class, 'store'])->name('company.contacts.store');
    Route::get('company/contacts/{type}/{id}/edit', [ContactController::class, 'edit'])->name('company.contacts.edit');
    Route::put('company/contacts/{type}/{id}', [ContactController::class, 'update'])->name('company.contacts.update');
    Route::delete('company/contacts/{type}/{id}', [ContactController::class, 'destroy'])->name('company.contacts.destroy');
});
