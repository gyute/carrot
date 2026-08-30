<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\ToolController;
use Illuminate\Support\Facades\Route;

// Approvals: department managers (first stage) and admins (second stage).
Route::middleware(['auth', 'can:reviewer'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('approvals/{submission}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('approvals/{submission}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('approvals/{submission}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('approvals/{submission}/test-run', [ApprovalController::class, 'testRun'])
        ->middleware('throttle:tool-runs')
        ->name('approvals.test-run');
});

// System administration: admins only.
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('tools/{tool}/deprecate', [ToolController::class, 'deprecate'])->name('tools.deprecate');
    Route::post('tools/{tool}/restore', [ToolController::class, 'restore'])->name('tools.restore');
    Route::delete('tools/{tool}', [ToolController::class, 'destroy'])->name('tools.destroy');
});
