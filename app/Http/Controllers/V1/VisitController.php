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
use App\Http\Requests\GetNextVisitDetailsRequest;
use App\Models\Visit;
use App\Http\Resources\TaskResource;
use App\Http\Resources\MedicationResource;
use App\Http\Requests\GetVisitRequest;
use Exception;

class VisitController extends Controller
{
    public function __construct(
        protected VisitService $visitService
    ) {}

        public function index(GetNextVisitDetailsRequest $request, Patient $patient): JsonResponse
    {
        try {
            $visitDetails = $this->visitService->getVisitDetails($patient);
            $nextVisit = $visitDetails
                ->filter(fn (Visit $visit) => $visit->next_visit_date
                    && $visit->next_visit_date->greaterThanOrEqualTo(now())
                    && $visit->status !== 'attended'
                )
                ->sortBy('next_visit_date')
                ->first();
            $data = [
                'tasks' => TaskResource::collection($visitDetails->flatMap->tasks),
                'medications' => MedicationResource::collection($visitDetails->flatMap->medications),
                'next_visit_id' => $nextVisit?->id,
                'next_visit_date' =>$nextVisit?->next_visit_date
                    ? $nextVisit->next_visit_date->format('D, M j, Y g:i A')
                    : null,
            ];

            return ApiResponse::success(message: 'Visit details retrieved successfully.', data: $data);
        } catch (Exception $e) {
            Log::error('Show Visit Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while fetching visit details.', status: 500);
        }
    }

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

    public function edit(GetVisitRequest $request, Visit $visit): JsonResponse
    {
        try{
            return ApiResponse::success(message: 'Next Visit date  retrieved successfully.', data:[
                'next_visit_date' => $visit->next_visit_date->format('Y-m-d H:i:s'),
            ]);
        }catch (Exception $e) {
            Log::error('Edit Visit Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while fetching visit details.', status: 500);
        }
    }
}