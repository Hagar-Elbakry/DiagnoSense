<?php

namespace App\Http\Controllers\V1\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegistrationRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthenticationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AuthenticationController extends Controller
{
    public function __construct(
        protected AuthenticationService $authenticationService
    ) {}

    public function register(RegistrationRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['is_active'] = true;
            $result = $this->authenticationService->register($data);

            return ApiResponse::success(
                message: 'User registered successfully',
                data: [
                    'user' => (new UserResource($result['user']))->additional(['user_id' => $result['userId']]),
                    'token' => $result['token'],
                ],
                status: 201
            );
        } catch (Exception $e) {
            Log::error('Error registering user: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to register user, please try again later.', status: 500);
        }
    }

    public function login(LoginRequest $request, string $type): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->authenticationService->login($data, $type);
            if (! $result) {
                return ApiResponse::error(message: 'Invalid credentials', status: 401);
            }

            return ApiResponse::success(
                message: 'Login successful',
                data: [
                    'user' => (new UserResource($result['user']))->additional(['user_id' => $result['userId']]),
                    'token' => $result['token'],
                ],
            );
        } catch (Exception $e) {
            Log::error('Error logging in: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to login, please try again later.', status: 500);
        }
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $this->authenticationService->logout($user);

            return ApiResponse::success(message: 'Logout successful');
        } catch (Exception $e) {
            Log::error('Error logging out: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to logout, please try again later.', status: 500);
        }
    }
}
