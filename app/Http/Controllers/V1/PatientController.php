<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePatientRequest;
use App\Http\Requests\GetPatientDataForUpdateRequest;
use App\Http\Requests\PatientListRequest;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdateFcmTokenRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Requests\UpdatePatientStatusRequest;
use App\Http\Resources\PatientEditResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{
    public function __construct(
        protected PatientService $patientService
    ) {}

    public function index(PatientListRequest $request): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor;
            $patients = $this->patientService->getPaginatedPatients($doctor, $request->validated());

            return ApiResponse::success(
                message: 'Patients list retrieved successfully',
                data: PatientResource::collection($patients)->response()->getData(true),
            );

        } catch (Exception $e) {
            Log::error('Patient Index Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while fetching patients.', status: 500);
        }
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        try {
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
        } catch (Exception $e) {
            Log::error('Patient Store Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while creating patient.', status: 500);
        }
    }

    public function triggerAiAnalysis(Request $request, Patient $patient): JsonResponse
    {
        try {
            $analysis = $this->patientService->runAiAnalysis(doctor: $request->user()->doctor, patient: $patient, isReAnalysis: true);

            return ApiResponse::success(
                message: 'AI Is Processing Now Due To Upgrade',
                data: [
                    'analysis_id' => $analysis->id,
                ]
            );
        } catch (Exception $e) {
            Log::error('AI Analysis Trigger Error: '.$e->getMessage());

            return ApiResponse::error(message: 'AI Analysis Trigger failed: '.$e->getMessage(), status: 500);
        }
    }

    public function edit(GetPatientDataForUpdateRequest $request, Patient $patient)
    {
        try {
            $patient = $this->patientService->getPatientEditData($patient);

            return ApiResponse::success(
                message: 'Data retrieved successfully',
                data: new PatientEditResource($patient)
            );
        } catch (Exception $e) {
            Log::error('Error retrieving patient data for edit: '.$e->getMessage(), ['id' => $patient->id]);

            return ApiResponse::error(message: 'An error occurred while retrieving patient data for edit.', status: 500);
        }
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        try {
            $doctor = $request->user()->doctor;
            $this->patientService->update($doctor, $patient, $request->validated());

            return ApiResponse::success(message: 'Patient file updated successfully');
        } catch (Exception $e) {
            Log::error('Update Error: '.$e->getMessage());

            return ApiResponse::error(message: 'Update failed: '.$e->getMessage(), status: 500);
        }
    }

    public function updateStatus(UpdatePatientStatusRequest $request, Patient $patient): JsonResponse
    {
        try {

            $this->patientService->updatePatientStatus($patient, $request->validated()['status']);

            return ApiResponse::success(message: 'Patient status updated successfully');

        } catch (Exception $e) {

            Log::error('Patient Status Update Error: '.$e->getMessage(), ['id' => $patient->id]);

            return ApiResponse::error(message: 'Failed to update patient status.', status: 500);
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

    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {
        try {
            $request->user()->update(['fcm_token' => $request->validated()['fcm_token']]);

            return ApiResponse::success(message: 'FCM Token Updated Successfully');
        } catch (Exception $e) {
            Log::error('FCM Token Update Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while updating the FCM token.', status: 500);
        }
    }
}
