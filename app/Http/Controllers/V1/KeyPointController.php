<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualNoteRequest;
use App\Models\Patient;
use App\Services\KeyPointService;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\KeyPointResource;
use Exception;

class KeyPointController extends Controller
{
    public function __construct(
        protected KeyPointService $keyPointService
    ){}

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
}