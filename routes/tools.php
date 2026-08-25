<?php

use App\Http\Controllers\ToolController;
use App\Http\Controllers\Tools\ExportController;
use App\Http\Controllers\Tools\ExportJobController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('tools', [ToolController::class, 'index'])->name('tools.index');

    Route::get('tools/exports', [ExportController::class, 'create'])->name('tools.exports.create');
    Route::post('tools/exports', [ExportController::class, 'store'])->name('tools.exports.store');

    Route::get('tools/exports/jobs', [ExportJobController::class, 'index'])->name('tools.exports.jobs');
    Route::post('tools/exports/jobs/lookup', [ExportJobController::class, 'lookup'])
        ->middleware('throttle:20,1')
        ->name('tools.exports.jobs.lookup');
    Route::get('tools/exports/jobs/{exportJob}/download', [ExportJobController::class, 'download'])
        ->name('tools.exports.jobs.download');
});
