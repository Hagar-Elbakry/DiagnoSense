<?php

use App\Http\Controllers\V1\Auth\AuthenticationController;
use App\Http\Controllers\V1\Auth\ContactVerificationController;
use App\Http\Controllers\V1\Auth\PasswordController;
use App\Http\Controllers\V1\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthenticationController::class, 'register'])->name('auth.register');
        Route::controller(SocialAuthController::class)->prefix('google')->as('google.')->group(function () {
            Route::get('/redirect', 'redirectToGoogle')->name('redirect');
            Route::get('/callback', 'handleGoogleCallback')->name('callback');
        });

        Route::middleware('check-user-type')->group(function () {
            Route::controller(AuthenticationController::class)->group(function () {
                Route::post('/login/{type}', 'login')->name('login')->middleware('throttle:login');
                Route::post('/logout/{type}', 'logout')->name('logout')->middleware('auth:sanctum');
            });

            Route::controller(PasswordController::class)->as('password.')->group(function () {
                Route::post('/forget-password/{type}', 'forgetPassword')->name('forget');
                Route::post('/verify-otp/{type}', 'verifyOtp')->name('verify');
                Route::post('/reset-password/{type}', 'resetPassword')->name('reset')->middleware(['auth:sanctum', 'abilities:reset-password']);
            });
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::controller(ContactVerificationController::class)->group(function () {
                Route::post('/verify-contact', 'verifyContact')->name('verify-contact');
                Route::post('/resend-otp', 'resendOtp')->name('resend-otp');
            });
        });
    });
});
