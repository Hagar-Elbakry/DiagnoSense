<?php

use App\Helpers\ApiResponse;
use App\Http\Middleware\CheckAiAccess;
use App\Http\Middleware\CheckUserType;
use App\Http\Middleware\EnsureContactIsVerifiedMiddleware;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ForceJsonResponse::class);

        $middleware->alias([
            'verified.contact' => EnsureContactIsVerifiedMiddleware::class,
            'check-user-type' => CheckUserType::class,
            'check-ai-access' => CheckAiAccess::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e) {
            return ApiResponse::error(
                message: 'Validation Errors',
                data: $e->errors(),
                status: 422
            );
        });

        $exceptions->render(function (NotFoundHttpException $e) {
            return ApiResponse::error(
                message: 'The requested resource was not found.',
                status: 404
            );
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if($request->is('api/*') || $request->expectsJson()) {
                if($e instanceof ValidationException) {
                    return null;
                }
                $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

                $message = $statusCode === 500
                    ? 'Failed to process request, please try again later.'
                    : $e->getMessage();
                return ApiResponse::error(
                    message: $message,
                    status: $statusCode
                );
            }
        });
    })->create();
