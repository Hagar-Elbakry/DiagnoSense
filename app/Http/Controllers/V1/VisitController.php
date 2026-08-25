<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNextVisitRequest;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use App\Services\VisitService;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\NextVisitResource;
use Exception;

class VisitController extends Controller
{
    public function __construct(
        protected VisitService $visitService
    ) {}

    public function store(StoreNextVisitRequest $request, Patient $patient): JsonResponse
    {
        try {
            $data = $request->validated();
            $doctor = $request->user()->doctor;
            $nextVisit = $this->visitService->store($data, $patient, $doctor);

            return ApiResponse::success(message: 'Visit created successfully.', data: new NextVisitResource($nextVisit));
        } catch (Exception $e) {
            Log::error('Store Visit Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while creating visit.', status: 500);
        }
    }
}