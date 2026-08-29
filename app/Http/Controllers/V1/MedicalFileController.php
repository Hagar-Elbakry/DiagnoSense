<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetPatientMedicalFilesRequest;
use App\Http\Resources\MedicalFileResource;
use App\Services\MedicalFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class MedicalFileController extends Controller
{
    public function __construct(
        protected MedicalFileService $medicalFileService
    ) {}

    public function __invoke(GetPatientMedicalFilesRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user || ! $user->patient) {
                return ApiResponse::error(message: 'Patient profile not found.', status: 404);
            }

            $files = $this->medicalFileService->getPatientFiles(
                patient: $user->patient,
                type: $request->query('type'),
                search: $request->query('search')
            );

            return ApiResponse::success(
                message: 'Medical files retrieved successfully.',
                data: MedicalFileResource::collection($files)
            );

        } catch (Exception $e) {
            Log::error('Error retrieving medical files: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while fetching medical files.', status: 500);
        }
    }
}