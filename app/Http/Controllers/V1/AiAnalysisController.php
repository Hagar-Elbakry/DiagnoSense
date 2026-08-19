<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetPatientOverviewRequest;
use App\Models\Patient;
use Illuminate\Support\Facades\Log;
use App\Helpers\ApiResponse;
use App\Services\AiAnalysisService;
use App\Http\Resources\PatientOverviewResource;
use Exception;

class AiAnalysisController extends Controller
{
    public function __construct(
        protected AiAnalysisService $aiAnalysisService
    ){}

    public function overview(GetPatientOverviewRequest $request, Patient $patient)
    {
        try{
            $patient = $this->aiAnalysisService->getPatientOverview($patient);
            return ApiResponse::success(
                message: 'Patient overview retrieved successfully.',
                data: new PatientOverviewResource($patient)
            );
        } catch(Exception $e) {
            Log::error('Error fetching patient overview: ' . $e->getMessage(), ['id' => $patient->id]);

            return ApiResponse::error(
                message: 'Failed to retrieve patient data.',
                status: 500
            );
        }
    }
}