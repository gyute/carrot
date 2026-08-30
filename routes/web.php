<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::inertia('/', 'home')->name('home');
});

require __DIR__.'/settings.php';
require __DIR__.'/tools.php';
require __DIR__.'/admin.php';
require __DIR__.'/inbox.php';
