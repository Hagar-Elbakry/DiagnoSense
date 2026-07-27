<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthenticationService;
use App\Http\Requests\Auth\RegistrationRequest;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;
use App\Http\Resources\UserResource; 
use Illuminate\Support\Facades\Log;
use Exception;

class AuthenticationController extends Controller
{
    public function __construct(
        protected AuthenticationService $authenticationService
    ){}

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
}
