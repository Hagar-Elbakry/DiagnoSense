<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use Illuminate\Support\Facades\Log;
use App\Helpers\ApiResponse;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\DeletePatientRequest;
use App\Http\Requests\GetPatientDataForUpdateRequest;
use App\Http\Requests\PatientListRequest;
use App\Models\Patient;
use App\Http\Resources\PatientResource;
use App\Http\Resources\PatientEditResource;
use Exception;


class PatientController extends Controller
{

    public function __construct(
        protected PatientService $patientService
    ){}

    public function index(PatientListRequest $request): JsonResponse
    {
        try{
            $doctor = $request->user()->doctor;
            $patients = $this->patientService->getPaginatedPatients($doctor, $request->validated());
            return ApiResponse::success(
                message: 'Patients list retrieved successfully',
                data: PatientResource::collection($patients)->response()->getData(true),
            );

        } catch(Exception $e){
            Log::error('Patient Index Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while fetching patients.', status: 500);
        }
    }

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

    public function edit(GetPatientDataForUpdateRequest $request, Patient $patient)
    {
        try{
            $patient = $this->patientService->getPatientEditData($patient);
            
            return ApiResponse::success(
                message: 'Data retrieved successfully',
                data: new PatientEditResource($patient)
            );
        } catch(Exception $e){
            Log::error('Error retrieving patient data for edit: '.$e->getMessage(), ['id' => $patient->id]);

            return ApiResponse::error(message: 'An error occurred while retrieving patient data for edit.', status: 500);
        }
    }

    public function destroy(DeletePatientRequest $request, Patient $patient): JsonResponse
    {

        try {
            $this->patientService->deletePatient($patient);
            return ApiResponse::success(
                message: 'Patient deleted successfully.'
            );

        } catch (Exception $e) {
            Log::error('Error deleting patient: '.$e->getMessage(), ['id' => $patient->id]);

            return ApiResponse::error(
                message: 'Failed to delete patient, please try again later.',
                status: 500
            );
        }
    }
}