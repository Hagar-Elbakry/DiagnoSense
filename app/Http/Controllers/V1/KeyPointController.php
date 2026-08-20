<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualNoteRequest;
use App\Models\Patient;
use App\Services\KeyPointService;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;
use App\Http\Requests\DeleteKeyInfoRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\PatientKeyInfoResource;
use App\Http\Resources\KeyPointResource;
use App\Models\KeyPoint;
use Exception;

class KeyPointController extends Controller
{
    public function __construct(
        protected KeyPointService $keyPointService
    ){}

    public function index(Patient $patient): JsonResponse
    {
        try{
            $result = $this->keyPointService->getPatientKeyInfo($patient);

            return ApiResponse::success(
                message: $result['message'],
                data: new PatientKeyInfoResource($result['data']),
            );
        } catch(Exception $e) {
            Log::error("Error retrieving key info for Patient {$patient->id}: ".$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while fetching key information.',
                status: 500
            );
        }
    }

    public function store(StoreManualNoteRequest $request, Patient $patient): JsonResponse
    {
        try{
            $keyPoint = $this->keyPointService->storeManualNote($patient, $request->validated());

            return ApiResponse::success(
                message: 'Doctor Manual key point added successfully',
                data: new KeyPointResource($keyPoint),
                status: 201
            );
        } catch(Exception $e) {
            Log::error('Error adding manual note: '.$e->getMessage());

            return ApiResponse::error(message: 'Error while adding manual note', status: 500);
        }
    }

    public function destroy(DeleteKeyInfoRequest $request, Patient $patient, KeyPoint $key_point): JsonResponse
    {
        try{
            $this->keyPointService->deleteKeyPoint($key_point);

            return ApiResponse::success(message: 'Key point deleted successfully');
        } catch(Exception $e) {
            Log::error('Error deleting key point: '.$e->getMessage(), ['id' => $key_point->id]);

            return ApiResponse::error(message: 'Error while deleting key point', status: 500);
        }
    }
}