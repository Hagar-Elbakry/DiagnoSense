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

        Route::middleware('check-user-type')->group(function () {
            Route::controller(AuthenticationController::class)->group(function () {
                Route::post('/login/{type}','login')->name('login')->middleware('throttle:login');
                Route::post('/logout/{type}', 'logout')->name('logout')->middleware('auth:sanctum');
            });
        });
    });
});