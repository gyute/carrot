<?php

use App\Http\Controllers\Inbox\MessageController;
use App\Http\Controllers\Inbox\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('inbox', [MessageController::class, 'index'])->name('inbox.index');
    Route::post('inbox/read-all', [MessageController::class, 'readAll'])->name('inbox.read-all');
    Route::get('inbox/{message}', [MessageController::class, 'show'])->name('inbox.show');
    Route::patch('inbox/{message}/read', [MessageController::class, 'read'])->name('inbox.read');

    Route::patch('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});
