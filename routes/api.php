<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\AuthenticationController;
use App\Http\Controllers\V1\Auth\SocialAuthController;

Route::prefix('v1')->group(function (){
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthenticationController::class, 'register'])->name('auth.register');
        Route::controller(SocialAuthController::class)->prefix('google')->as('google.')->group(function () {
        Route::get('/redirect', 'redirectToGoogle')->name('redirect');
        Route::get('/callback', 'handleGoogleCallback')->name('callback');
    });
    });
});