<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetPatientOverviewRequest;
use App\Http\Resources\DecisionSupportResource;
use App\Http\Resources\PatientOverviewResource;
use App\Models\Patient;
use App\Services\AiAnalysisService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AiAnalysisController extends Controller
{
    public function __construct(
        protected AiAnalysisService $aiAnalysisService
    ) {}

    public function overview(GetPatientOverviewRequest $request, Patient $patient): JsonResponse
    {
        try {
            $patient = $this->aiAnalysisService->getPatientOverview($patient);

            return ApiResponse::success(
                message: 'Patient overview retrieved successfully.',
                data: new PatientOverviewResource($patient)
            );
        } catch (Exception $e) {
            Log::error('Error fetching patient overview: '.$e->getMessage(), ['id' => $patient->id]);

            return ApiResponse::error(
                message: 'Failed to retrieve patient data.',
                status: 500
            );
        }
    }

    public function decisionSupport(Patient $patient): JsonResponse
    {
        try {
            $result = $this->aiAnalysisService->getPatientDecisionSupport($patient);

            return ApiResponse::success(
                message: $result['message'],
                data: [
                    'still_processing' => $result['data']['still_processing'],
                    'decisions' => DecisionSupportResource::collection($result['data']['decisions']),
                ]
            );
        } catch (Exception $e) {
            Log::error("Decision Support Error for Patient {$patient->id}: ".$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while fetching decision support information.',
                status: 500
            );
        }
    }

    public function comparativeAnalysis(Patient $patient): JsonResponse
    {
        try {
            $result = $this->aiAnalysisService->getPatientComparativeAnalysis($patient);

            return ApiResponse::success(
                message: $result['message'],
                data: $result['data']
            );
        } catch (Exception $e) {
            Log::error("Comparative Analysis Error for Patient {$patient->id}: ".$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while fetching comparative analysis.',
                status: 500
            );
        }
    }
}
