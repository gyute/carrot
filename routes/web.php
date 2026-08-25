<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');

require __DIR__.'/settings.php';
