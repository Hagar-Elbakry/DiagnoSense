<?php

namespace App\Http\Controllers\V1\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ExchangeSocialCodeRequest;
use App\Http\Requests\RedirectToGoogleRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\SocialAuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    public function __construct(
        protected SocialAuthService $socialAuthService
    ) {}

    public function redirectToGoogle(RedirectToGoogleRequest $request): JsonResponse
    {
        try {
            $url = $this->socialAuthService->getRedirectUrl('google', $request->validated('client_nonce'));

            return ApiResponse::success(message: 'Redirect URL generated', data: ['url' => $url]);
        } catch (Exception $e) {
            Log::error('Google Redirect Error: '.$e->getMessage());

            return ApiResponse::error(message: 'Unable to connect to Google at the moment', status: 500);
        }
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $result = $this->socialAuthService->handleProviderCallback(
                provider: 'google',
                state: $request->query('state')
            );

            $code = $this->socialAuthService->createExchangeCode(
                user: $result['user'],
                token: $result['token'],
                clientNonce: $result['client_nonce']
            );
            $frontendUrl = config('services.frontend.url');

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
            $result = $this->socialAuthService->exchangeCode(
                code: $request->validated('code'),
                providedNonce: $request->input('client_nonce')
            );

            return ApiResponse::success(
                message: 'Authenticated successfully',
                data: [
                    'user' => new UserResource($result['user']),
                    'doctor_id' => $result['doctor_id'],
                    'token' => $result['token'],
                ]
            );
        } catch (Exception $e) {
            return ApiResponse::error(message: $e->getMessage(), status: 400);
        }
    }
}
