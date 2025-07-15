<?php

use App\Http\Controllers\TimeTravelController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::prefix('travel')->as('travel.')->group(function () {
        Route::get('{user}/forward', [TimeTravelController::class, 'forward'])->name('forward');
        Route::put('{user}/back', [TimeTravelController::class, 'back'])->name('back');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
