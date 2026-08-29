<?php

namespace App\Http\Controllers\V1;

use App\Actions\UpdatePatientProfileAction;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePatientProfileRequest;
use App\Http\Resources\PatientProfileResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PatientProfileController extends Controller
{
    public function __invoke(UpdatePatientProfileRequest $request, UpdatePatientProfileAction $action): JsonResponse
    {

        try {
            $user = $request->user();
            if (! $user->patient) {
                return ApiResponse::error(message: 'Patient Profile Not Found', status: 404);
            }
            $data = $action->execute($user, $request->validated());

            return ApiResponse::success(
                message: 'Profile updated successfully',
                data: new PatientProfileResource($data),
            );

        } catch (Exception $e) {
            Log::error('Error updating profile: '.$e->getMessage(), ['user_id' => $request->user()?->id]);

            return ApiResponse::error(
                message: 'An error occurred while updating profile.',
                status: 500
            );
        }
    }
}
