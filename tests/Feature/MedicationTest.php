<?php

use App\Models\Medication;

beforeEach(function () {
    $this->user = createUserWithType('doctor', fake()->unique()->safeEmail());
    $this->patient = createUserWithType('patient', fake()->unique()->safeEmail());
    $this->user->doctor->patients()->attach($this->patient->patient->id);
    $this->visit = createVisit($this->user->doctor, $this->patient->patient);
    $this->medication = [
        'name' => 'Paracetamol',
        'dosage' => '100mg',
        'frequency' => 'daily',
        'duration' => '10 days',
        'action' => 'save',
    ];

    $this->medication1 = Medication::factory()->create([
        'visit_id' => $this->visit->id,
        'name' => 'Panadol',
        'dosage' => '500mg',
        'frequency' => 'Once daily',
        'duration' => '5 days',
    ]);

    $this->medication2 = Medication::factory()->create([
        'visit_id' => $this->visit->id,
        'name' => 'Catafast',
        'dosage' => '50mg',
        'frequency' => 'Twice daily',
        'duration' => null,
    ]);
});

it('returns the correct medications list', function () {
    $response = $this->actingAs($this->patient, 'sanctum')->getJson(route('medications.index'));

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Patient medications retrieved successfully')
        ->assertJsonStructure([
            'data' => [
                '*' => ['name', 'dosage', 'frequency', 'duration'],
            ],
        ])
        ->assertJsonFragment([
            'name' => 'Panadol',
            'dosage' => '500mg',
            'frequency' => 'Once daily',
            'duration' => '5 days',
        ])
        ->assertJsonFragment([
            'name' => 'Catafast',
            'dosage' => '50mg',
            'frequency' => 'Twice daily',
            'duration' => 'N/A',
        ]);
});

it('allows doctor to add medication to visit successfully', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson(route('visits.medications.store', ['visit' => $this->visit->id]), $this->medication);
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'id',
            'name',
            'dosage',
            'frequency',
            'duration',
            'action',
            'created_at',
            'updated_at',
        ],
    ]);
    $this->assertDatabaseHas('medications', [
        'name' => $this->medication['name'],
        'visit_id' => $this->visit->id,
    ]);
    $this->assertDatabaseHas('visits', [
        'id' => $this->visit->id,
        'status' => 'completed',
    ]);
});

it('prevents doctor from adding medication to unassigned patient', function () {
    $otherDoctor = createUserWithType('doctor', fake()->unique()->safeEmail());
    $response = $this->actingAs($otherDoctor, 'sanctum')->postJson(route('visits.medications.store', ['visit' => $this->visit->id]), $this->medication);
    $response->assertStatus(403);
});

it('allows doctor to delete medication successfully', function () {
    $medication = $this->visit->medications()->create($this->medication);
    $response = $this->actingAs($this->user, 'sanctum')->deleteJson(route('medications.destroy', ['medication' => $medication->id]));
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Medication deleted successfully',
        'data' => null,
    ]);
    $this->assertDatabaseMissing('medications', ['id' => $medication->id]);
});
