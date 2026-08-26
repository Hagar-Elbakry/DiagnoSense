<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicationRequest;
use App\Http\Resources\DoctorMedicationResource;
use App\Models\Visit;
use App\Services\MedicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class MedicationController extends Controller
{
    public function __construct(
        protected MedicationService $medicationService
    ) {}

    public function store(StoreMedicationRequest $request, Visit $visit): JsonResponse
    {
        try {
            $data = $request->validated();
            $medication = $this->medicationService->store($visit, $data);

            return ApiResponse::success(message: 'Medication created successfully', data: new DoctorMedicationResource($medication));
        } catch (Exception $e) {
            Log::error('Store Medication Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while creating medication.', status: 500);
        }
    }
}