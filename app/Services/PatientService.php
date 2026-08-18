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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Services\SubscriptionService;
use Exception;

class PatientService 
{
    public function __construct(
        protected ReportService $reportService,
        protected SubscriptionService $subscriptionService
    ){}

    public function getPaginatedPatients(Doctor $doctor, array $params): LengthAwarePaginator
    {
        $query = User::query()
            ->select(['users.id', 'users.name'])
            ->join('patients', 'patients.user_id', '=', 'users.id')
            ->join('doctor_patient', 'doctor_patient.patient_id', '=', 'patients.id')
            ->where('doctor_patient.doctor_id', $doctor->id)
            ->whereNull('patients.deleted_at');

            $query->when(! empty($params['search']), function ($q) use ($params) {
            $term = $params['search'];
            $q->where(function ($sub) use ($term) {
                if (is_numeric($term)) {
                    $sub->where('patients.national_id', 'LIKE', $term.'%');
                } else {
                    $sub->where('users.name', 'LIKE', $term.'%');
                }
            });
        });

        $query->when(! empty($params['status']), function ($q) use ($params) {
            $q->where('patients.status', $params['status']);
        });

        return $query->with([
            'patient:id,user_id,date_of_birth,status,created_at,national_id',
            'patient.latestAiAnalysisResult:id,patient_id,ai_insight',
        ])
            ->latest('users.created_at')
            ->paginate(12)
            ->appends($params);
    }

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

    public function getPatientEditData(Patient $patient)
    {
        return $patient->load([
            'user',
            'medicalHistory',
            'reports',
        ]);
    }

    public function update(Doctor $doctor, Patient $patient, array $data): void
    {
        DB::transaction(function () use ($doctor, $patient, $data) {
            $userData = $this->only($data, ['name', 'contact']);

            if (! empty($userData)) {
                $patient->user->update($userData);
            }

            $patientData = $this->only($data, [
                'gender',
                'date_of_birth',
                'national_id',
            ]);
            
            if (! empty($patientData)) {
                $patient->update($patientData);
            }

            $complaintChanged = false;

            $medicalHistoryData = $this->only($data, [
                'current_complaints',
                'is_smoker',
                'chronic_diseases',
                'previous_surgeries_name',
                'current_medications',
                'allergies',
                'family_history',
            ]);

            if (! empty($medicalHistoryData)) {
                $medicalHistory = $patient->medicalHistory()->updateOrCreate(
                    ['patient_id' => $patient->id],
                    $medicalHistoryData
                );

                $complaintChanged = $medicalHistory->wasChanged('current_complaints');
            }

            $reportsTypes = ['lab', 'radiology', 'medical_history'];
            $newPathsForAI = ['lab' => [], 'radiology' => [], 'medical_history' => []];

            $newPathsForAI = $this->reportService->getPathsForAI($reportsTypes, $data, $patient, $newPathsForAI);
            $hasNewFiles = ! empty(array_filter($newPathsForAI));

            if ($hasNewFiles || $complaintChanged) {
                $this->runAiAnalysis(
                    doctor: $doctor,
                    patient: $patient,
                    newPaths: $newPathsForAI,
                    isReAnalysis: false,
                    isFromUpdate: true
                );
            }
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

    private function runAiAnalysis(Doctor $doctor, Patient $patient, array $newPaths = [], bool $isReAnalysis = false,bool $isFromUpdate = false): void
    {
        $this->subscriptionService->validateAiAccess($doctor);

        if ($isReAnalysis) {
            $analysisResult = $patient->latestAiAnalysisResult;

            if (! $analysisResult) {
                throw new Exception('No existing analysis found to upgrade.', 422);
            }

            $analysisResult->update(['status' => 'processing']);
        } else {
            $analysisResult = $patient->latestAiAnalysisResult()->create([
                'status' => 'processing',
            ]);
        }

        $allPaths = $newPaths;
        if (empty(array_filter($newPaths)) && !$isFromUpdate) {
            $allPaths = $patient->reports->groupBy('type')->map(fn ($group) => $group->pluck('file_path')->toArray())->toArray();
        }

        $jobData = $this->getJobData($patient, $doctor, $patient->medicalHistory, $allPaths, $isReAnalysis);

        $this->triggerAnalysisWorkflows($analysisResult, $jobData, $allPaths, $patient);
    }

    private function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }
}