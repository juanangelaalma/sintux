<?php

use Illuminate\Support\Facades\Route;
use Modules\Approval\Http\Controllers\ApprovalActionController;
use Modules\Approval\Http\Controllers\ApprovalCommentController;
use Modules\Approval\Http\Controllers\ApprovalInboxController;
use Modules\Approval\Http\Controllers\ApprovalRuleController;
use Modules\Company\Http\Middleware\EnsureCompanyMember;

// Approval rule management and approval inbox routes.
Route::middleware(['auth', 'verified', EnsureCompanyMember::class])->group(function () {
    Route::prefix('approval')->name('approval.')->group(function () {
        Route::get('rules', [ApprovalRuleController::class, 'index'])
            ->name('rules.index');

        Route::get('rules/create', [ApprovalRuleController::class, 'create'])
            ->name('rules.create');

        Route::post('rules', [ApprovalRuleController::class, 'store'])
            ->name('rules.store');

        Route::get('rules/{rule}/edit', [ApprovalRuleController::class, 'edit'])
            ->name('rules.edit');

        Route::put('rules/{rule}', [ApprovalRuleController::class, 'update'])
            ->name('rules.update');

        Route::delete('rules/{rule}', [ApprovalRuleController::class, 'destroy'])
            ->name('rules.destroy');

        Route::get('rules/{rule}/logs', [ApprovalRuleController::class, 'logs'])
            ->name('rules.logs');

        Route::get('inbox', [ApprovalInboxController::class, 'index'])
            ->name('inbox.index');

        Route::post('mappings/{mapping}/approve', [ApprovalActionController::class, 'approve'])
            ->name('mappings.approve');

        Route::post('mappings/{mapping}/reject', [ApprovalActionController::class, 'reject'])
            ->name('mappings.reject');

        Route::post('comments', [ApprovalCommentController::class, 'store'])
            ->name('comments.store');
    });
});
