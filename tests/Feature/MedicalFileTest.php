<?php

use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('azure');

    $this->patientUser = User::factory()->create(['type' => 'patient']);
    $this->patient = Patient::factory()->create([
        'user_id' => $this->patientUser->id,
    ]);

    $this->actingAs($this->patientUser, 'sanctum');
});

it('returns medical files successfully by type', function (string $type) {
    Report::create([
        'patient_id' => $this->patient->id,
        'type' => $type,
        'file_name' => 'test_file_1.pdf',
        'file_path' => 'reports/test_file_1.pdf',
    ]);

    Report::create([
        'patient_id' => $this->patient->id,
        'type' => $type,
        'file_name' => 'test_file_2.png',
        'file_path' => 'reports/test_file_2.png',
    ]);

    $response = $this->getJson(route('patient.medical-files.index', ['type' => $type]));

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Medical files retrieved successfully.')
        ->assertJsonCount(2, 'data');

    $fileData = $response->json('data.0');
    expect($fileData)->toHaveKeys(['id', 'name', 'referred_by', 'date', 'extension', 'file_url']);
    expect($fileData['file_url'])->not->toBeNull();

})->with(['medical_history', 'lab', 'radiology']);

it('filters medical files accurately using search query', function () {
    Report::create([
        'patient_id' => $this->patient->id,
        'type' => 'lab',
        'file_name' => 'blood_cbc_report.pdf',
        'file_path' => 'reports/cbc.pdf',
    ]);

    Report::create([
        'patient_id' => $this->patient->id,
        'type' => 'lab',
        'file_name' => 'urine_test.pdf',
        'file_path' => 'reports/urine.pdf',
    ]);

    $response = $this->getJson(route('patient.medical-files.index', [
        'type' => 'lab',
        'search' => 'cbc',
    ]));

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'blood_cbc_report.pdf');
});