<?php

use Illuminate\Support\Facades\Storage;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\AiAnalysisResult;
use App\Models\DecisionSupport;
use App\Models\PatientLabResult;

beforeEach(function(){
    Storage::fake('azure');
    $this->doctorUser = createDoctorWithBilling();
    $this->patient = Patient::factory()->create();
    $this->doctorUser->doctor->patients()->attach($this->patient->id);
    $this->actingAs($this->doctorUser, 'sanctum');
    $this->medicalHistory = MedicalHistory::factory()->create([
        'patient_id' => $this->patient->id,
    ]);
});

it('allows doctor to view overview for their patient', function () {
    $response = $this->getJson(route('patients.overview', $this->patient->id));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'patientId',
            'patientName',
            'doctorName',
            'smart_summary',
            'age',
            'smoker',
            'chronicDiseases',
            'previousSurgeries',
            'allergies',
            'medications',
            'familyHistory',
            'status',
        ]
    ]);
});

it('denies doctor to view overview for someone else\'s patient', function () {
    $otherDoctor = createDoctorWithBilling();
    $response = $this->actingAs($otherDoctor, 'sanctum')->getJson(route('patients.overview', $this->patient->id));
    $response->assertStatus(403);
});

it('indicates that decision support is still processing when the first analysis is running', function () {
    AiAnalysisResult::factory()->create([
        'patient_id' => $this->patient->id,
        'status' => 'processing',
    ]);

    $response = $this->getJson(route('patients.decision-support', $this->patient->id));

    $response->assertStatus(200)
        ->assertJsonPath('data.still_processing', true)
        ->assertJsonPath('message', 'AI analysis for decision support is still running.');
});

it('indicates that decision support is ready while analysis is still running', function () {
    $analysis = AiAnalysisResult::factory()->create([
        'patient_id' => $this->patient->id,
        'status' => 'processing',
    ]);

    DecisionSupport::factory()->create([
        'ai_analysis_result_id' => $analysis->id,
        'condition' => 'Initial Diagnosis',
    ]);

    $response = $this->getJson(route('patients.decision-support', $this->patient->id));

    $response->assertStatus(200)
        ->assertJsonPath('data.still_processing', false)
        ->assertJsonCount(1, 'data.decisions')
        ->assertJsonPath('message', 'decision support retrieved successfully but comparative analysis is still running.');
});

it('shows historical decisions while new analysis is processing', function () {
    $oldAnalysis = AiAnalysisResult::factory()->create([
        'patient_id' => $this->patient->id,
        'status' => 'completed',
        'created_at' => now()->subHour(),
    ]);

    DecisionSupport::factory()->create([
        'ai_analysis_result_id' => $oldAnalysis->id,
        'condition' => 'Old Condition',
    ]);

    AiAnalysisResult::factory()->create([
        'patient_id' => $this->patient->id,
        'status' => 'processing',
        'created_at' => now(),
    ]);

    $response = $this->getJson(route('patients.decision-support', $this->patient->id));

    $response->assertStatus(200)
        ->assertJsonPath('data.still_processing', true)
        ->assertJsonPath('message', 'Showing old decision support. Some files are still being processed.')
        ->assertJsonFragment(['condition' => 'Old Condition']);
});

it('calculates trends and percentages correctly for multiple lab results', function () {
    PatientLabResult::factory()->create([
        'patient_id' => $this->patient->id,
        'standard_name' => 'Hemoglobin',
        'numeric_value' => '10',
        'created_at' => now()->subDays(2),
    ]);

    PatientLabResult::factory()->create([
        'patient_id' => $this->patient->id,
        'standard_name' => 'Hemoglobin',
        'numeric_value' => '12',
        'created_at' => now(),
    ]);

    $response = $this->getJson(route('patients.comparative-analysis', $this->patient));

    $response->assertStatus(200)
        ->assertJsonPath('data.analysis.0.test_name', 'Hemoglobin')
        ->assertJsonPath('data.analysis.0.comparison.current_value', 12)
        ->assertJsonPath('data.analysis.0.comparison.previous_value', 10)
        ->assertJsonPath('data.analysis.0.comparison.change_percentage', 20)
        ->assertJsonPath('data.analysis.0.comparison.trend', 'up');
});
