<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteKeyInfoRequest;
use App\Http\Requests\StoreManualNoteRequest;
use App\Http\Requests\UpdateKeyPointRequest;
use App\Http\Resources\KeyPointResource;
use App\Http\Resources\PatientKeyInfoResource;
use App\Models\KeyPoint;
use App\Models\Patient;
use App\Services\KeyPointService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class KeyPointController extends Controller
{
    public function __construct(
        protected KeyPointService $keyPointService
    ) {}

    public function index(Patient $patient): JsonResponse
    {
        try {
            $result = $this->keyPointService->getPatientKeyInfo($patient);

            return ApiResponse::success(
                message: $result['message'],
                data: new PatientKeyInfoResource($result['data']),
            );
        } catch (Exception $e) {
            Log::error("Error retrieving key info for Patient {$patient->id}: ".$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while fetching key information.',
                status: 500
            );
        }
    }

    public function store(StoreManualNoteRequest $request, Patient $patient): JsonResponse
    {
        try {
            $keyPoint = $this->keyPointService->storeManualNote($patient, $request->validated());

            return ApiResponse::success(
                message: 'Doctor Manual key point added successfully',
                data: new KeyPointResource($keyPoint),
                status: 201
            );
        } catch (Exception $e) {
            Log::error('Error adding manual note: '.$e->getMessage());

            return ApiResponse::error(message: 'Error while adding manual note', status: 500);
        }
    }

    public function update(UpdateKeyPointRequest $request, Patient $patient, KeyPoint $key_point): JsonResponse
    {
        try {
            $this->keyPointService->updateKeyPoint($key_point, $request->validated());

            return ApiResponse::success(message: 'Key point updated successfully');

        } catch (Exception $e) {
            Log::error('Error updating key point: '.$e->getMessage(), ['id' => $key_point->id]);

            return ApiResponse::error(message: 'Error while updating key point', status: 500);
        }
    }

    public function destroy(DeleteKeyInfoRequest $request, Patient $patient, KeyPoint $key_point): JsonResponse
    {
        try {
            $this->keyPointService->deleteKeyPoint($key_point);

            return ApiResponse::success(message: 'Key point deleted successfully');
        } catch (Exception $e) {
            Log::error('Error deleting key point: '.$e->getMessage(), ['id' => $key_point->id]);

            return ApiResponse::error(message: 'Error while deleting key point', status: 500);
        }
    }
}
