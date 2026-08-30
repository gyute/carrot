<?php

use App\Http\Controllers\ToolController;
use App\Http\Controllers\Tools\StudioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('tools', [ToolController::class, 'index'])->name('tools.index');

    Route::get('tools/studio/{page?}', [StudioController::class, 'show'])->name('tools.studio');
});
