<?php

use App\Models\AiAnalysisResult;
use Illuminate\Support\Facades\Storage;
use App\Models\Patient;
use App\Models\KeyPoint;

beforeEach(function(){
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

it('can delete key point successfully', function () {

    $response = $this->deleteJson(
        route('key-points.destroy',  $this->keyPoint),
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