<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgetPasswordRequest;
use App\Services\Auth\AuthenticationService;
use App\Helpers\ApiResponse;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Exception;

class PasswordController extends Controller {
    
    public function  __construct(
        protected AuthenticationService $authenticationService
    ){}

    public function forgetPassword(ForgetPasswordRequest $request, string $type): JsonResponse
    {
        try {
            $data = $request->validated();
            $this->authenticationService->forgetPassword($data);

            return ApiResponse::success(message: 'OTP has been sent to your registered contact.');
        } catch (Exception $e) {
            Log::error('Forget Password Error: '.$e->getMessage());

            return ApiResponse::error(message: 'Failed to process request.', status: 500);
        }
    }

    public function verifyOtp(VerifyOtpRequest $request, string $type): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->authenticationService->verifyOtp($data);
            if(!$result)
            {
                return ApiResponse::error(
                    message: 'Invalid Or Expired OTP.',
                    status: 401
                );
            }

            return ApiResponse::success(
                message: 'OTP verified. You can now reset your password.',
                data: ['reset_token' => $result]
            );
        } catch (Exception $e) {
            Log::error('Unexpected OTP Error: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(
                message: 'An unexpected error occurred. Please try again later.',
                status: 500
            );
        }
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try{
            $user = $request->user();
            $data = $request->validated();

            $this->authenticationService->resetPassword($user, $data['password']);
            return ApiResponse::success(message: 'Your password has been reset');
        }catch(Exception $e) {
            Log::error('Password Reset Error: '.$e->getMessage());

            return ApiResponse::error(message: 'Failed to reset password.', status: 500);
        }
    }
}