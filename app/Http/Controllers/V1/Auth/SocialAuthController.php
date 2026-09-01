<?php

namespace App\Http\Controllers\V1\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ExchangeSocialCodeRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\SocialAuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function __construct(
        protected SocialAuthService $socialAuthService
    ) {}

    public function redirectToGoogle(): JsonResponse
    {
        try {
            $url = $this->socialAuthService->getRedirectUrl('google');

            return ApiResponse::success(message: 'Redirect URL generated', data: ['url' => $url]);
        } catch (Exception $e) {
            Log::error('Google Redirect Error: '.$e->getMessage());

            return ApiResponse::error(message: 'Unable to connect to Google at the moment', status: 500);
        }
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $result = $this->socialAuthService->handleProviderCallback('google', $request->query('state'));
            $frontendUrl = config('services.frontend.url');
            $code = $this->socialAuthService->createExchangeCode($result['user'], $result['token']);

            return redirect()->to("{$frontendUrl}/auth/callback?code={$code}");

        } catch (Exception $e) {
            Log::error('Social login failed', [
                'provider' => 'google',
                'error' => $e->getMessage(),
            ]);

            return redirect()->to(config('services.frontend.url').'?message=auth_failed');
        }
    }

    public function exchangeSocialCode(ExchangeSocialCodeRequest $request): JsonResponse
    {
        try {
            $result = $this->socialAuthService->exchangeCode($request->validated('code'));

            return ApiResponse::success(
                message: 'Authenticated successfully',
                data: [
                    'user' => new UserResource($result['user']),
                    'doctor_id' => $result['doctor_id'],
                    'token' => $result['token'],
                ]
            );
        } catch (Exception $e) {
            return ApiResponse::error(message: $e->getMessage());
        }
    }
}
