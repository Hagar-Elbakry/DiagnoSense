<?php

namespace App\Providers;

use App\Helpers\ApiResponse;
use App\Models\KeyPoint;
use App\Models\Patient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('registration', function (Request $request) {
            $key = $request->ip().'|'.($request->input('contact') ?? '');

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->contact.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return ApiResponse::error(
                        message: 'Too many login attempts. Retry after '.$headers['Retry-After'].' seconds.',
                        status: 429
                    );
                });
        });

        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                Dsn::fromString(config('services.brevo.dsn'))
            );
        });

        Route::model('patient', Patient::class);
        Route::model('$key_point', KeyPoint::class);
    }
}
