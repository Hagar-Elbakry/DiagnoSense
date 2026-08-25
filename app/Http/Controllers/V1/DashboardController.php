<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;
use App\Services\DashboardService;
use App\Http\Resources\TopDiseaseResource;
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
}