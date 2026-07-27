<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\AuthenticationController;

Route::prefix('v1')->group(function (){
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthenticationController::class, 'register'])->name('auth.register');
    });
});