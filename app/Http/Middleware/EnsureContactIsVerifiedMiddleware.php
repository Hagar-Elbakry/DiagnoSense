<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContactIsVerifiedMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->contact_verified_at) {
            return ApiResponse::error(
                message: 'Your contact is not verified. Please verify your account first.',
                status: 403
            );
        }

        return $next($request);
    }
}
