<?php

use App\Http\Controllers\TimeTravelController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->as('api.')->middleware(['auth:api'])->group(function () {
    Route::post('{user}/travel', [TimeTravelController::class, 'travel'])->name('travel');
    Route::post('{user}/return', [TimeTravelController::class, 'return'])->name('return');
});
