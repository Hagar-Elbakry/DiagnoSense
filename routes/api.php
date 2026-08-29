<?php

use App\Http\Controllers\V1\AiAnalysisController;
use App\Http\Controllers\V1\Auth\AuthenticationController;
use App\Http\Controllers\V1\Auth\ContactVerificationController;
use App\Http\Controllers\V1\Auth\PasswordController;
use App\Http\Controllers\V1\Auth\SocialAuthController;
use App\Http\Controllers\V1\ChatbotController;
use App\Http\Controllers\V1\DashboardController;
use App\Http\Controllers\V1\DoctorProfileController;
use App\Http\Controllers\V1\KeyPointController;
use App\Http\Controllers\V1\MedicationController;
use App\Http\Controllers\V1\PatientController;
use App\Http\Controllers\V1\PaymobWebhookController;
use App\Http\Controllers\V1\SubscriptionController;
use App\Http\Controllers\V1\SupportController;
use App\Http\Controllers\V1\TaskController;
use App\Http\Controllers\V1\VisitController;
use App\Http\Controllers\V1\WalletController;
use App\Http\Controllers\V1\WebNotificationController;
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

        Route::controller(PatientController::class)->prefix('patients')->as('patients.')->group(function () {
            Route::get('', 'index')->name('index');
            Route::middleware('check-ai-access')->group(function () {
                Route::post('', 'store')->name('store');
                Route::post('/{patient}/re-analyze', 'triggerAiAnalysis')->name('re-analyze');
            });
            Route::get('{patient}/edit', 'edit')->name('edit');
            Route::patch('/{patient}', 'update')->name('update');
            Route::patch('{patient}/status', 'updateStatus')->name('update-status');
            Route::delete('/{patient}', 'destroy')->name('destroy');
        });

        Route::controller(AiAnalysisController::class)->prefix('patients')->as('patients.')->group(function () {
            Route::get('/{patient}/overview', 'overview')->name('overview');
            Route::middleware('can:view,patient')->group(function () {
                Route::get('/{patient}/decision-support', 'decisionSupport')->name('decision-support');
                Route::get('/{patient}/comparative-analysis', 'comparativeAnalysis')->name('comparative-analysis');
            });
        });

        Route::post('patients/{patient}/chatbot/ask', ChatbotController::class)->name('chatbot.ask')->middleware('check-ai-access');

        Route::apiResource('patients.key-points', KeyPointController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->shallow()->middlewareFor('index', 'can:view,patient');

        Route::controller(DashboardController::class)->prefix('dashboard')->as('dashboard.')->group(function () {
            Route::get('/status-distribution', 'statusDistribution')->name('status-distribution');
            Route::get('/top-diseases', 'topDiseases')->name('top-diseases');
            Route::get('/summary', 'summary')->name('summary');
            Route::get('/today-visits', 'todayVisits')->name('todayVisits');
        });
        Route::apiResource('patients.visits', VisitController::class)->only(['index', 'store', 'edit', 'update'])->shallow();
        Route::apiResource('visits.tasks', TaskController::class)->only(['store', 'destroy'])->shallow();
        Route::apiResource('visits.medications', MedicationController::class)->only(['store', 'destroy'])->shallow();
        Route::patch('/fcm-token', [PatientController::class, 'updateFcmToken'])->name('patients.fcm-token');

        Route::controller(DoctorProfileController::class)->prefix('doctors')->as('doctor.')->group(function () {
            Route::get('/profile/edit', 'edit')->name('profile.edit');
            Route::patch('/profile', 'update')->name('profile.update');
            Route::delete('/profile', 'destroy')->name('profile.destroy');
            Route::patch('/change-password', 'changePassword')->name('password.update');
        });

        Route::post('/support', SupportController::class)->name('support.create');
        
        Route::controller(WebNotificationController::class)->prefix('notifications')->as('notifications.')->group(function(){
            Route::get('/', 'index')->name('index');
            Route::get('/unread-count', 'unreadCount')->name('unreadCount');
        });
    });
});

Route::get('/payment-redirect', function (Request $request) {
    if ($request->query('success') === 'true') {
        return redirect(config('services.frontend.url').'/subscription?status=success');
    }
});

Route::post('/paymob/webhook', [PaymobWebhookController::class, 'handle'])->name('paymob.webhook');
