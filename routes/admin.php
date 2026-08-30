<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\RunController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\ToolController;
use App\Http\Controllers\Admin\ToolRequestController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Approvals: department managers (first stage) and admins (second stage).
Route::middleware(['auth', 'can:reviewer', 'feature:submissions'])->prefix('admin')->name('admin.')->group(function () {
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
    Route::get('tools', [ToolController::class, 'index'])->name('tools.index');
    Route::post('tools/{tool}/deprecate', [ToolController::class, 'deprecate'])->name('tools.deprecate');
    Route::post('tools/{tool}/restore', [ToolController::class, 'restore'])->name('tools.restore');
    Route::delete('tools/{tool}', [ToolController::class, 'destroy'])->name('tools.destroy');
    // Deleted tools are soft-deleted, so route model binding would not find
    // them; these two take the ULID and look in the trash themselves.
    Route::post('tools/{ulid}/untrash', [ToolController::class, 'untrash'])->name('tools.untrash');
    Route::delete('tools/{ulid}/purge', [ToolController::class, 'purge'])->name('tools.purge');

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::patch('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

    Route::get('runs', [RunController::class, 'index'])->name('runs.index');
    Route::delete('runs/{run}', [RunController::class, 'destroy'])->name('runs.destroy');
    Route::post('runs/prune', [RunController::class, 'prune'])->name('runs.prune');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'retire'])->name('users.retire');

    Route::get('system', [SystemController::class, 'index'])->name('system.index');
});

// The development team's request queue: one stage, admins only.
Route::middleware(['auth', 'can:admin', 'feature:requests'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('requests', [ToolRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/{toolRequest}', [ToolRequestController::class, 'show'])->name('requests.show');
    Route::post('requests/{toolRequest}/accept', [ToolRequestController::class, 'accept'])->name('requests.accept');
    Route::post('requests/{toolRequest}/start', [ToolRequestController::class, 'start'])->name('requests.start');
    Route::post('requests/{toolRequest}/decline', [ToolRequestController::class, 'decline'])->name('requests.decline');
    Route::post('requests/{toolRequest}/duplicate', [ToolRequestController::class, 'duplicate'])->name('requests.duplicate');
    Route::post('requests/{toolRequest}/deliver', [ToolRequestController::class, 'deliver'])->name('requests.deliver');
});
