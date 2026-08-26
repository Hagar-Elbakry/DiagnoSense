<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;
use App\Services\DashboardService;
use App\Http\Resources\TopDiseaseResource;
use App\Http\Resources\WidgetDashboardResource;
use App\Http\Resources\CurrentVisitResource;
use App\Http\Resources\VisitQueueResource;
use Exception;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function statusDistribution(Request $request): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor;
            if (! $doctor) {
                return ApiResponse::error(message: 'Doctor not found', status: 404);
            }
            $chartData = $this->dashboardService->getPatientStatusChartData($doctor);

            return ApiResponse::success(
                message: 'Status distribution retrieved successfully',
                data: $chartData
            );
        } catch (Exception $e) {
            Log::error('Error retrieving status distribution: '.$e->getMessage());

            return ApiResponse::error(message: 'Failed to retrieve status distribution', status: 500);
        }
    }

    public function topDiseases(Request $request): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor;
            if (! $doctor) {
                return ApiResponse::error(message: 'Doctor not found', status: 404);
            }
            $topDiseases = $this->dashboardService->getTopChronicDiseases($doctor);

            return ApiResponse::success(
                message: 'Top 5 chronic diseases retrieved successfully',
                data: TopDiseaseResource::collection($topDiseases)
            );
        } catch (Exception $e) {
            Log::error('Error retrieving top diseases: '.$e->getMessage());

            return ApiResponse::error(message: 'Failed to retrieve top diseases', status: 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor;

            $stats = $this->dashboardService->getSummary($doctor);

            return ApiResponse::success(
                message: 'Dashboard summary retrieved successfully',
                data: new WidgetDashboardResource($stats),
            );
        } catch (Exception $e) {
            Log::error('Error retrieving dashboard summary: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to retrieve dashboard summary, please try again later.', status: 500);
        }
    }

    public function todayVisits(Request $request): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor;

            $todayVisits = $this->dashboardService->getTodayVisits($doctor);
            $currentPatient = $todayVisits->first();

            request()->merge([
                'current_patient_id' => $currentPatient?->patient->id,
            ]);

            return ApiResponse::success(
                message: 'Queue retrieved successfully',
                data: [
                    'current_attending' => $currentPatient
                        ? new CurrentVisitResource($currentPatient)
                        : null,

                    'full_queue_list' => $todayVisits->isNotEmpty()
                        ? VisitQueueResource::collection($todayVisits->take(5))
                        : null,

                    'remaining_count_label' => max($todayVisits->count() - 1, 0).' remaining',
                ]
            );
        } catch (Exception $e) {
            Log::error(
                "Error retrieving today's visits: {$e->getMessage()}",
                ['exception' => $e]
            );

            return ApiResponse::error(
                message: "Failed to retrieve today's visits, please try again later.",
                status: 500
            );
        }
    }
}