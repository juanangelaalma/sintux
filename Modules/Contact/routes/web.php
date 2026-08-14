<?php

use Illuminate\Support\Facades\Route;
use Modules\Contact\Http\Controllers\ContactController;

Route::middleware(['auth', 'company.member'])->group(function () {
    Route::get('company/contacts/{type}/create', [ContactController::class, 'create'])
        ->middleware('permission:contact.create')
        ->name('company.contacts.create');
    Route::get('company/contacts/{type}', [ContactController::class, 'index'])
        ->middleware('permission:contact.view')
        ->name('company.contacts.index');
    Route::post('company/contacts/{type}', [ContactController::class, 'store'])
        ->middleware('permission:contact.create')
        ->name('company.contacts.store');
    Route::get('company/contacts/{type}/{id}/edit', [ContactController::class, 'edit'])
        ->middleware('permission:contact.update')
        ->name('company.contacts.edit');
    Route::put('company/contacts/{type}/{id}', [ContactController::class, 'update'])
        ->middleware('permission:contact.update')
        ->name('company.contacts.update');
    Route::delete('company/contacts/{type}/{id}', [ContactController::class, 'destroy'])
        ->middleware('permission:contact.delete')
        ->name('company.contacts.destroy');
});
