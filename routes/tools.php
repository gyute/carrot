<?php

use App\Http\Controllers\ToolController;
use App\Http\Controllers\Tools\SubmissionController;
use App\Http\Controllers\Tools\ToolRunController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('tools', [ToolController::class, 'index'])->name('tools.index');

    // Requests to register or change a tool. The static paths come before
    // `tools/{tool}` so "submissions" is never read as a tool ULID.
    Route::get('tools/submissions', [SubmissionController::class, 'index'])->name('tools.submissions.index');
    Route::get('tools/submissions/create', [SubmissionController::class, 'create'])->name('tools.submissions.create');
    Route::post('tools/submissions', [SubmissionController::class, 'store'])->name('tools.submissions.store');
    Route::get('tools/submissions/{submission}', [SubmissionController::class, 'show'])->name('tools.submissions.show');
    Route::get('tools/submissions/{submission}/edit', [SubmissionController::class, 'edit'])->name('tools.submissions.edit');
    Route::patch('tools/submissions/{submission}', [SubmissionController::class, 'update'])->name('tools.submissions.update');
    Route::post('tools/submissions/{submission}/submit', [SubmissionController::class, 'submit'])->name('tools.submissions.submit');
    Route::delete('tools/submissions/{submission}', [SubmissionController::class, 'destroy'])->name('tools.submissions.destroy');

    Route::get('tools/{tool}', [ToolController::class, 'show'])->name('tools.show');
    Route::patch('tools/{tool}', [ToolController::class, 'update'])->name('tools.update');
    Route::get('tools/{tool}/change', [SubmissionController::class, 'create'])->name('tools.change.create');
    Route::post('tools/{tool}/change', [SubmissionController::class, 'store'])->name('tools.change.store');
    Route::post('tools/{tool}/deprecate', [SubmissionController::class, 'deprecate'])->name('tools.deprecate');
    Route::post('tools/{tool}/runs', [ToolRunController::class, 'store'])
        ->middleware('throttle:tool-runs')
        ->name('tools.runs.store');
    Route::get('tools/{tool}/runs/{run}', [ToolRunController::class, 'show'])->name('tools.runs.show');
});
