<?php

use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('tools', [ToolController::class, 'index'])->name('tools.index');

    Route::get('tools/{tool}', [ToolController::class, 'show'])->name('tools.show');
});
