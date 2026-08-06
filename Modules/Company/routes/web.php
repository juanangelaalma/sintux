<?php

use App\Http\Middleware\EnsureCompanyMember;
use App\Http\Middleware\EnsureSuperadmin;
use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Controllers\AdminRoleController;
use Modules\Company\Http\Controllers\CompanyBranchController;
use Modules\Company\Http\Controllers\CompanyController;
use Modules\Company\Http\Controllers\CompanyUserController;

Route::middleware(['auth', 'verified', EnsureSuperadmin::class])->group(function () {
    Route::resource('admin/companies', CompanyController::class)->except(['create', 'show', 'edit'])->names('admin.companies');

    Route::get('admin/roles', [AdminRoleController::class, 'index'])->name('admin.roles.index');
    Route::put('admin/roles/{role}', [AdminRoleController::class, 'update'])->name('admin.roles.update');
});

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::resource('company/users', CompanyUserController::class)->except(['create', 'show', 'edit'])->names('company.users');

    Route::resource('company/branches', CompanyBranchController::class)->only(['index', 'store', 'update'])->names('company.branches');
});
