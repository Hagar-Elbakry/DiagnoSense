<?php

use App\Jobs\AiAnalysisJob;
use App\Models\MedicalHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Http::fake();
    Storage::fake('azure');
    $this->doctorUser = createDoctorWithBilling();
    $this->patientUser = createUserWithType('patient', 'testPatient@gmail.com', 'UniquePatientName');
    $this->stablePatient = createUserWithType('patient', 'stablePatient@gmail.com');
    $this->criticalPatient = createUserWithType('patient', 'criticalPatient@gmail.com');
    $this->doctorUser->doctor->patients()->attach([
        $this->patientUser->patient->id,
        $this->stablePatient->patient->id,
        $this->criticalPatient->patient->id,
    ]);
    $this->stablePatient->patient->update(['status' => 'stable']);
    $this->criticalPatient->patient->update(['status' => 'critical']);
    $this->actingAs($this->doctorUser, 'sanctum');
    $this->validPatientData = validPatientData();
    $this->medicalHistory = MedicalHistory::factory()->create([
        'patient_id' => $this->patientUser->patient->id,
    ]);
    $this->patientUser->patient->reports()->create([
        'type' => 'lab',
        'file_name' => 'test_report.pdf',
        'file_path' => 'reports/test_report.pdf',
        'mime_type' => 'application/pdf',
    ]);
});

it('gets patients list', function () {
    $response = $this->getJson(route('patients.index'));
    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data.data');
    $response->assertJsonStructure([
        'data' => [
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'age',
                    'status',
                    'ai_insight',
                    'last_visit',
                    'next_appointment'
                ],
            ],
            'links',
            'meta',
        ],
    ]);
});

it('allows doctor to search by name', function () {
    $response = $this->getJson(route('patients.index', ['search' => $this->patientUser->name]));
    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data.data');
});

it('allows doctor to search by national id', function () {
    $this->patientUser->patient->update(['national_id' => '123456789']);
    $response = $this->getJson(route('patients.index', ['search' => $this->patientUser->patient->national_id]));
    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data.data');
});

it('allows doctor to filter by status', function () {
    $response = $this->getJson(route('patients.index', ['status' => $this->stablePatient->patient->status]));
    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data.data');
});

it('allow doctor to create patient successfully', function () {
    $response = $this->postJson(route('patients.store'), $this->validPatientData);
    $response->assertStatus(201);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'patient_id',
            'analysis_result_id',
        ],
    ]);
    $this->assertDatabaseHas('users', [
        'contact' => $this->validPatientData['contact'],
        'type' => 'patient',
    ]);
    $this->assertDatabaseHas('patients', [
        'date_of_birth' => $this->validPatientData['date_of_birth'],
        'gender' => $this->validPatientData['gender'],
        'national_id' => $this->validPatientData['national_id'],
    ]);
    $this->assertDatabaseHas('medical_histories', [
        'patient_id' => $response->json('data.patient_id'),
        'is_smoker' => $this->validPatientData['is_smoker'],
        'previous_surgeries_name' => $this->validPatientData['previous_surgeries_name'],
        'current_medications' => $this->validPatientData['current_medications'],
        'allergies' => $this->validPatientData['allergies'],
        'family_history' => $this->validPatientData['family_history'],
        'current_complaints' => $this->validPatientData['current_complaints'],
    ]);

    $medicalHistory = MedicalHistory::where(
        'patient_id',
        $response->json('data.patient_id')
    )->first();

    expect($medicalHistory->chronic_diseases)
        ->toBe($this->validPatientData['chronic_diseases']);

    foreach (['lab', 'radiology', 'medical_history'] as $reportType) {
        $this->assertDatabaseHas('reports', [
            'patient_id' => $response->json('data.patient_id'),
            'type' => $reportType,
            'file_name' => $this->validPatientData[$reportType][0]->getClientOriginalName(),
            'mime_type' => $this->validPatientData[$reportType][0]->getMimeType(),
        ]);
    }
    $this->assertDatabaseHas('doctor_patient', [
        'doctor_id' => $this->doctorUser->doctor->id,
        'patient_id' => $response->json('data.patient_id'),
    ]);
    Queue::assertPushed(AiAnalysisJob::class);
    $this->assertDatabaseHas('ai_analysis_results', [
        'patient_id' => $response->json('data.patient_id'),
        'status' => 'processing',
    ]);
});

it('if fails validation when contact or files are invalid', function (array $invalidData, array $expectedErrors) {
    $response = $this->postJson(route('patients.store'), $invalidData);
    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Validation Errors',
        'data' => $expectedErrors,
    ]);
})->with([
    'invalid contact' => [fn () => array_merge(validPatientData(), ['contact' => 'invalid']), ['contact' => ['The contact must be a valid email address or a valid phone number starting with 010, 011, 012, or 015 followed by 8 digits.']]],
    'no files' => [[array_diff_key(validPatientData(), ['lab' => [], 'radiology' => [], 'medical_history' => []])], ['lab' => ['Please upload at least one lab test result or radiology report or medical history report.'], 'radiology' => ['Please upload at least one lab test result or radiology report or medical history report.'], 'medical_history' => ['Please upload at least one lab test result or radiology report or medical history report.']]],
]);

it('if fails validation when contact is already taken', function () {
    createUserWithType('patient', $this->validPatientData['contact']);
    $response = $this->postJson(route('patients.store'), $this->validPatientData);
    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Validation Errors',
        'data' => ['contact' => ['The contact has already been taken.']],
    ]);
});

it('it returns patient data for editing', function () {
    $response = $this->getJson(route('patients.edit', ['patient' => $this->patientUser->patient->id]));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'personal_info' => [
                'name',
                'contact',
                'date_of_birth',
                'gender',
                'national_id',
            ],
            'medical_history',
            'existing_files' => [
                '*' => [
                    'id',
                    'type',
                    'name',
                    'url',
                ],
            ],
        ],
    ]);
});

it('it denies doctor from getting another doctor patient edit data', function () {
    $otherDoctor = createDoctorWithBilling();
    $response = $this->actingAs($otherDoctor, 'sanctum')->getJson(route('patients.edit', ['patient' => $this->patientUser->patient->id]));
    $response->assertStatus(403);
});

it('allows doctor to update patient data', function () {
    $response = $this->patchJson(route('patients.update', ['patient' => $this->patientUser->patient->id]), [
        'name' => 'Updated Name',
        'contact' => 'updated@gmail.com',
        'date_of_birth' => '1990-01-01',
        'gender' => 'male',
    ]);
    $response->assertStatus(200);
    $this->assertDatabaseHas('users', [
        'name' => 'Updated Name',
        'contact' => 'updated@gmail.com',
    ]);
});

it('denies doctor from updating someone else\'s patient data', function () {
    $otherDoctor = createDoctorWithBilling();
    $response = $this->actingAs($otherDoctor, 'sanctum')->patchJson(route('patients.update', ['patient' => $this->patientUser->patient->id]), [
        'name' => 'Updated Name',
    ]);
    $response->assertStatus(403);
});

it('updates patient status successfully', function () {
    $this->patchJson(
        route('patients.update-status', [
            'patient' => $this->patientUser->patient->id,
        ]),
        [
            'status' => 'critical',
        ]
    )
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Patient status updated successfully',
            'data' => null
        ]);
        expect(
            $this->patientUser->patient->fresh()->status
        )->toBe('critical');
});

it('allows doctor to delete their patient', function () {
    $response = $this->actingAs($this->doctorUser, 'sanctum')->deleteJson(route('patients.destroy', ['patient' => $this->patientUser->patient->id]));
    $response->assertStatus(200);
    $this->assertSoftDeleted('patients', ['id' => $this->patientUser->patient->id]);
    $response->assertJson([
        'message' => 'Patient deleted successfully.',
    ]);
});

it('denies doctor from delete someone else\'s patient', function () {
    $otherDoctor = createDoctorWithBilling();
    $otherPatient = createUserWithType('patient', 'otherPatient@gmail.com');
    $otherDoctor->doctor->patients()->attach($otherPatient->patient->id);
    $response = $this->actingAs($this->doctorUser, 'sanctum')->deleteJson(route('patients.destroy', ['patient' => $otherPatient->patient->id]));
    $response->assertStatus(403);
});
