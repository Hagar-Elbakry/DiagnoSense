<?php

use App\Models\MedicalHistory;

beforeEach(function(){
    $this->doctorUser = createDoctorWithBilling();
    $this->patientUser = createUserWithType('patient', 'testPatient@gmail.com');
    $this->doctorUser->doctor->patients()->attach($this->patientUser->patient->id);
    $this->actingAs($this->doctorUser, 'sanctum');
    $this->medicalHistory = MedicalHistory::factory()->create([
        'patient_id' => $this->patientUser->patient->id,
    ]);
});

it('allows doctor to view overview for their patient', function () {
    $response = $this->getJson(route('patients.overview', $this->patientUser->patient->id));
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
    $response = $this->actingAs($otherDoctor, 'sanctum')->getJson(route('patients.overview', $this->patientUser->patient->id));
    $response->assertStatus(403);
});
