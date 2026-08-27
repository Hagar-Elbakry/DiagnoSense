<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDoctorProfileRequest;
use App\Http\Resources\DoctorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\DoctorService;
use Illuminate\Support\Facades\Log;
use Exception;

class DoctorProfileController extends Controller
{
    public function __construct(
        protected DoctorService $doctorService
    ) {}

    public function edit(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user = $this->doctorService->getDoctorProfileData($user);

            return ApiResponse::success(message: 'Doctor Information', data: new DoctorResource($user));
        } catch (Exception $e) {
            Log::error('Doctor Profile Error: '.$e->getMessage());

            return ApiResponse::error(message: 'Failed to fetch doctor profile', status: 500);
        }
    }

    public function update(UpdateDoctorProfileRequest $request): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor;
            $this->doctorService->updateProfile(
                doctor: $doctor,
                data: $request->validated()
            );

            return ApiResponse::success(message: 'Profile updated successfully');
        } catch (Exception $e) {
            Log::error('Error updating profile: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to update profile', status: 500);
        }
    }
}