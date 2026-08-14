<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Product\Http\Controllers\ProductCategoryController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\ProductHubController;
use Modules\Product\Http\Controllers\ProductVariantController;
use Modules\Product\Http\Controllers\UomController;

Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::prefix('product')->name('product.')->group(function () {
        Route::get('/', [ProductHubController::class, 'index'])->name('hub');
        // Categories
        Route::resource('categories', ProductCategoryController::class)
            ->parameters(['categories' => 'category'])
            ->names([
                'index' => 'categories.index',
                'create' => 'categories.create',
                'store' => 'categories.store',
                'edit' => 'categories.edit',
                'update' => 'categories.update',
                'destroy' => 'categories.destroy',
            ])
            ->except(['show']);

        // UOMs
        Route::resource('uoms', UomController::class)
            ->parameters(['uoms' => 'uom'])
            ->names([
                'index' => 'uoms.index',
                'create' => 'uoms.create',
                'store' => 'uoms.store',
                'edit' => 'uoms.edit',
                'update' => 'uoms.update',
                'destroy' => 'uoms.destroy',
            ])
            ->except(['show']);

        // Products
        Route::post('products/upload-image', [ProductController::class, 'uploadImage'])
            ->name('products.upload-image');

        Route::resource('products', ProductController::class)
            ->parameters(['products' => 'product'])
            ->names([
                'index' => 'products.index',
                'create' => 'products.create',
                'store' => 'products.store',
                'edit' => 'products.edit',
                'update' => 'products.update',
                'destroy' => 'products.destroy',
            ])
            ->except(['show']);

        // Variants (nested under products for 1:1 / variant compatibility)
        Route::prefix('products/{product}')->name('products.')->group(function () {
            Route::post('variants', [ProductVariantController::class, 'store'])->name('variants.store');
            Route::put('variants/{variant}', [ProductVariantController::class, 'update'])->name('variants.update');
            Route::delete('variants/{variant}', [ProductVariantController::class, 'destroy'])->name('variants.destroy');
        });
    });
});
