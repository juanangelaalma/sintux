<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Controllers\DashboardController;
use Modules\Company\Http\Middleware\EnsureCompanyUser;
use Modules\Company\Http\Middleware\EnsureSuperadmin;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified', EnsureCompanyUser::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'verified', EnsureSuperadmin::class])->group(function () {
    Route::inertia('admin/dashboard', 'admin/dashboard')->name('admin.dashboard');
});

require __DIR__.'/settings.php';
