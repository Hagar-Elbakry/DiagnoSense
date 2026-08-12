<?php

use App\Http\Controllers\V1\Auth\AuthenticationController;
use App\Http\Controllers\V1\Auth\ContactVerificationController;
use App\Http\Controllers\V1\Auth\PasswordController;
use App\Http\Controllers\V1\Auth\SocialAuthController;
use App\Http\Controllers\V1\PatientController;
use App\Http\Controllers\V1\PaymobWebhookController;
use App\Http\Controllers\V1\SubscriptionController;
use App\Http\Controllers\V1\WalletController;
use Illuminate\Http\Request;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::controller(WalletController::class)->prefix('wallets')->as('wallets.')->group(function () {
            Route::post('charge', 'store')->name('charge');
            Route::get('transactions', 'index')->name('transactions');
        });

        Route::controller(SubscriptionController::class)->prefix('subscriptions')->as('subscriptions.')->group(function () {
            Route::post('/{plan}/subscribe', 'store')->name('subscribe');
            Route::post('pay-per-use', 'switchToPayPerUse')->name('pay-per-use');
            Route::get('current', 'show')->name('current');
            Route::patch('cancel', 'update')->name('cancel');
            Route::get('plans', 'index')->name('plans');
        });

        Route::controller(PatientController::class)->prefix('patients')->as('patients.')->group(function(){
            Route::post('', 'store')->name('store')->middleware('check-ai-access');
            Route::delete('/{patient}', 'destroy')->name('destroy');
        });
    });
});

Route::get('/payment-redirect', function (Request $request) {
    if ($request->query('success') === 'true') {
        return redirect(config('services.frontend.url').'/subscription?status=success');
    }
});

Route::post('/paymob/webhook', [PaymobWebhookController::class, 'handle'])->name('paymob.webhook');
