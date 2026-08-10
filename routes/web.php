<?php

use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsureCompanyUser;
use App\Http\Middleware\EnsureSuperadmin;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified', EnsureCompanyUser::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'verified', EnsureSuperadmin::class])->group(function () {
    Route::inertia('admin/dashboard', 'admin/dashboard')->name('admin.dashboard');
});

require __DIR__.'/settings.php';
