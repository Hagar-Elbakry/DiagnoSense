<?php 

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\AiAnalysisResult;
use App\Models\MedicalHistory;
use Illuminate\Support\Str;
use App\Jobs\AiAnalysisJob;
use App\Jobs\ComparativeAnalysis;
use Illuminate\Support\Facades\Bus;

class PatientService 
{
    public function __construct(
        protected ReportService $reportService
    ){}

    public function store(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            $doctor = $user->doctor;
            $user = $this->storeUser($data);
            $patient = $this->storePatient($user, $data);
            $patient->doctors()->attach($doctor->id);
            $medicalHistory = $this->storeMedicalHistory($patient, $data);
            $reportsTypes = ['lab', 'radiology', 'medical_history'];
            $pathsForAI = [
                'lab' => [],
                'radiology' => [],
                'medical_history' => [],
            ];

            $pathsForAI = $this->reportService->getPathsForAI($reportsTypes, $data, $patient, $pathsForAI);
            $analysisResult = $patient->latestAiAnalysisResult()->create([
                'status' => 'processing',
            ]);

            $jobData = $this->getJobData($patient, $doctor, $medicalHistory, $pathsForAI);

            $this->triggerAnalysisWorkflows($analysisResult, $jobData, $pathsForAI, $patient);

            return compact('patient', 'analysisResult');
        });
    }

    public function deletePatient(Patient $patient): bool
    {
        return (bool) $patient->delete();
    }

    private function storeUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'contact' => $data['contact'],
            'type' => 'patient',
            'password' => Str::random(10),
        ]);

        return $user;
    }

    private function storePatient(User $user, array $data): Patient
    {
        $patient = $user->patient()->create([
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'] ?? null,
            'national_id' => $data['national_id'] ?? null,
        ]);

        return $patient;
    }

    private function storeMedicalHistory(Patient $patient, array $data): MedicalHistory
    {
        $medicalHistory = $patient->medicalHistory()->create([
            'is_smoker' => $data['is_smoker'] ?? null,
            'previous_surgeries_name' => $data['previous_surgeries_name'] ?? null,
            'chronic_diseases' => $data['chronic_diseases'] ?? null,
            'current_medications' => $data['current_medications'] ?? null,
            'allergies' => $data['allergies'] ?? null,
            'family_history' => $data['family_history'] ?? null,
            'current_complaints' => $data['current_complaints'] ?? null,
        ]);

        return $medicalHistory;
    }

    private function getJobData(Patient $patient, Doctor $doctor, MedicalHistory $medicalHistory, array $pathsForAI, bool $isReAnalysis = false): array
    {
        $jobData = [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'age' => $patient->age,
            'gender' => $patient->gender,
            'history' => $medicalHistory->toArray(),
            'file_paths' => $pathsForAI,
            'features' => [
                'decision_support' => $doctor->hasFeature('Decision Support'),
            ],
            'isReAnalysis' => $isReAnalysis,
        ];

        return $jobData;
    }

    private function triggerAnalysisWorkflows(
        AiAnalysisResult $analysisResult,
        array $jobData,
        array $pathsForAI,
        Patient $patient
    ): void {
        $chain = [
            new AiAnalysisJob($analysisResult->id, $jobData),
        ];
        $isReAnalysis = $jobData['isReAnalysis'] ?? false;
        if (! empty($pathsForAI['lab']) && ! $isReAnalysis) {
            $chain[] = new ComparativeAnalysis($patient, $analysisResult);
        }
        DB::afterCommit(function () use ($chain) {
            Bus::chain($chain)->dispatch();
        });
    }
}