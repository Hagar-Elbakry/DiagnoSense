<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthenticationService;
use App\Http\Requests\Auth\ContactVerificationRequest;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;

class ContactVerificationController extends Controller 
{
    public function __construct(
        protected AuthenticationService $authenticationService
    ) {}

    public function verifyContact(ContactVerificationRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = $request->user();
            $result = $this->authenticationService->verifyContact($data, $user);

            if (! $result) {
                return ApiResponse::error(
                    message: 'Invalid or expired OTP.',
                    status: 401
                );
            }

            return ApiResponse::success(
                message: 'User verified successfully.'
            );

        } catch (Exception $e) {
            Log::error('Error verifying contact: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(
                message: 'Failed to verify contact, please try again later.',
                status: 500
            );
        }
    }

    public function resendOtp(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->authenticationService->resendOtp($user);

            if (! $result) {
                return ApiResponse::error(
                    message: 'User already verified.',
                    status: 409
                );
            }

            return ApiResponse::success(
                message: 'OTP sent successfully.'
            );

        } catch (Exception $e) {
            Log::error('Error resending OTP: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(
                message: 'Failed to resend OTP, please try again later.',
                status: 500
            );
        }
    }
}