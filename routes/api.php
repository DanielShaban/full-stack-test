<?php

use App\Http\Controllers\TimeTravelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function () {
    Route::post('{user}/travel', [TimeTravelController::class, 'travel'])->name('travel');
    Route::put('{user}/return', [TimeTravelController::class, 'return'])->name('return');
    Route::patch('{user}/forward', [TimeTravelController::class, 'forward'])->name('forward');
    Route::patch('{user}/back', [TimeTravelController::class, 'back'])->name('back');
});
