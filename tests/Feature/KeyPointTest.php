<?php

use App\Models\AiAnalysisResult;
use App\Models\KeyPoint;
use App\Models\Patient;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('azure');
    $this->doctorUser = createDoctorWithBilling();
    $this->patient = Patient::factory()->create();
    $this->patient->doctors()->attach($this->doctorUser->doctor->id);
    $this->actingAs($this->doctorUser);
    $this->analysis = AiAnalysisResult::factory()->create([
        'patient_id' => $this->patient->id,
        'status' => 'completed',
    ]);

    $this->keyPoint = KeyPoint::factory()->create([
        'ai_analysis_result_id' => $this->analysis->id,
    ]);
});

it('can get analysis with key points', function () {
    $response = $this->getJson(route('patients.key-points.index', $this->patient));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'still_processing',
            'ocr_files',
            'key_points' => [
                'high' => [
                    '*' => [
                        'id',
                        'priority',
                        'title',
                        'insight',
                        'evidence',
                        'is_ai_generated',
                        'date',
                    ],
                ],
                'medium' => [
                    '*' => [
                        'id',
                        'priority',
                        'title',
                        'insight',
                        'evidence',
                        'is_ai_generated',
                        'date',
                    ],
                ],
                'low' => [
                    '*' => [
                        'id',
                        'priority',
                        'title',
                        'insight',
                        'evidence',
                        'is_ai_generated',
                        'date',
                    ],
                ],
            ],
        ],
    ]);
});

it('returns empty key points when no analysis exists', function () {
    $otherPatient = Patient::factory()->create();
    $otherPatient->doctors()->attach($this->doctorUser->doctor->id);
    $response = $this->getJson(route('patients.key-points.index', $otherPatient));
    $response->assertStatus(200);
    $response->assertJsonCount(0, 'data.key_points.high');
    $response->assertJsonCount(0, 'data.key_points.medium');
    $response->assertJsonCount(0, 'data.key_points.low');
});

it('returns still processing when analysis is running', function () {
    $this->analysis->update([
        'status' => 'processing',
    ]);
    $this->keyPoint->delete();
    $response = $this->getJson(route('patients.key-points.index', $this->patient));
    $response->assertStatus(200)
        ->assertJsonPath('data.still_processing', true);
});

it('denies unauthorized doctor from getting key points', function () {
    $otherDoctor = createDoctorWithBilling();
    $response = $this->actingAs($otherDoctor, 'sanctum')->getJson(route('patients.key-points.index', $this->patient));
    $response->assertStatus(403);
});

it('can add a new manual note successfully', function () {

    AiAnalysisResult::factory()->create([
        'patient_id' => $this->patient->id,
        'status' => 'completed',
    ]);

    $payload = [
        'insight' => 'Patient should follow a strict diet.',
        'priority' => 'medium',
    ];

    $response = $this->postJson(
        route('patients.key-points.store', $this->patient),
        $payload
    );

    $response->assertStatus(201)
        ->assertJsonPath(
            'message',
            'Doctor Manual key point added successfully'
        )
        ->assertJsonPath(
            'data.insight',
            $payload['insight']
        )
        ->assertJsonPath(
            'data.is_ai_generated',
            'Doctor Note'
        );

    $this->assertDatabaseHas('key_points', [
        'insight' => $payload['insight'],
        'priority' => 'medium',
        'is_ai_generated' => false,
    ]);
});

it('fails to add a manual note when insight is missing', function () {

    $response = $this->postJson(
        route('patients.key-points.store', $this->patient),
        [
            'priority' => 'high',
        ]
    );

    $response->assertStatus(422)
        ->assertJsonPath(
            'data.insight.0',
            'The insight field is required.'
        );
});

it('can update key point', function () {
    $response = $this->patchJson(route('key-points.update', $this->keyPoint), [
        'insight' => 'Updated insight',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath(
            'message',
            'Key point updated successfully'
        );

    $this->assertDatabaseHas('key_points', [
        'id' => $this->keyPoint->id,
        'insight' => 'Updated insight',
    ]);
});

it('can delete key point successfully', function () {

    $response = $this->deleteJson(
        route('key-points.destroy', $this->keyPoint),
    );

    $response->assertStatus(200)
        ->assertJsonPath(
            'message',
            'Key point deleted successfully'
        );

    $this->assertSoftDeleted('key_points', [
        'id' => $this->keyPoint->id,
    ]);
});

it('returns 403 when deleting key point for unauthorized doctor', function () {

    $otherDoctorUser = createDoctorWithBilling();

    $response = $this->actingAs($otherDoctorUser, 'sanctum')->deleteJson(
        route('key-points.destroy', $this->keyPoint)
    );

    $response->assertStatus(403)
        ->assertJsonPath(
            'message',
            'This action is unauthorized.'
        );
});
