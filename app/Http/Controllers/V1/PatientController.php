<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use Illuminate\Support\Facades\Log;
use App\Helpers\ApiResponse;
use App\Services\PatientService;
use Exception;
use Illuminate\Http\JsonResponse;

class PatientController extends Controller
{

    public function __construct(
        protected PatientService $patientService
    ){}

    public function store(StorePatientRequest $request): JsonResponse
    {
        try{
            $data = $request->validated();
            $user = $request->user();
            $result = $this->patientService->store($data, $user);

            return ApiResponse::success(
                message: 'Patient created successfully and AI analysis is in progress.',
                data : [
                    'patient_id' => $result['patient']->id,
                    'analysis_result_id' => $result['analysisResult']->id,
                ],
                status: 201
            );
        } catch(Exception $e){
            Log::error('Patient Store Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while creating patient.', status: 500);
        }
    }
}