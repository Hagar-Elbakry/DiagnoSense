<?php

use App\Models\Task;
use App\Models\Medication;

beforeEach(function () {
    $userDoctor = createUserWithType('doctor', fake()->unique()->safeEmail());
    $userPatient = createUserWithType('patient', fake()->unique()->safeEmail());
    $this->doctor = $userDoctor->doctor;
    $this->patient = $userPatient->patient;
    $this->doctor->patients()->attach($this->patient);
    $this->actingAs($userDoctor, 'sanctum');
    $this->visit = createVisit($this->doctor, $this->patient, today());
    $this->task = Task::create([
        'title' => 'Task 1',
        'description' => 'Task description',
        'visit_id' => $this->visit->id,
    ]);
    $this->medication = Medication::create([
        'name' => 'Paracetamol',
        'dosage' => '100mg',
        'frequency' => 'daily',
        'visit_id' => $this->visit->id,
    ]);
});

it('allows doctor to view visit details', function () {
    $response = $this->get(route('patients.visits.index', ['patient' => $this->patient->id]));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'tasks' => [
                [
                    'id',
                    'title',
                    'description',
                    'notes',
                    'is_completed',
                    'action',
                    'due_date',
                    'created_at',
                    'updated_at',
                ],
            ],
            'medications' => [
                [
                    'id',
                    'name',
                    'dosage',
                    'frequency',
                    'duration',
                    'action',
                    'created_at',
                    'updated_at',
                ],
            ],
            'next_visit_date',
        ],
    ]);
});

it('allow doctor to create visit successfully', function () {
    $date = now()->addDays(7)->toDateTimeString();
    $response = $this->post(route('patients.visits.store', ['patient' => $this->patient->id]), [
        'has_next_visit' => true,
        'next_visit_date' => $date,
        'action' => 'save',
    ]);
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'id',
            'next_visit_date',
            'status',
            'doctor_name',
            'specialization',
            'date',
            'time',
        ],
    ]);
    $this->assertDatabaseHas('visits', [
        'patient_id' => $this->patient->id,
        'doctor_id' => $this->doctor->id,
        'next_visit_date' => $date,
        'status' => 'completed',
    ]);
});

it('prevents doctor from creating visit for unassigned patient', function () {
    $otherDoctor = createUserWithType('doctor', fake()->unique()->safeEmail());
    $response = $this->actingAs($otherDoctor, 'sanctum')->post(route('patients.visits.store', ['patient' => $this->patient->id]), [
        'has_next_visit' => true,
        'next_visit_date' => now()->addDays(7)->toDateTimeString(),
        'action' => 'save',
    ]);
    $response->assertStatus(403);
});

it('allows doctor to update visit successfully', function () {
    $response = $this->patch(route('visits.update', ['visit' => $this->visit->id]), [
        'next_visit_date' => now()->addDays(7)->toDateTimeString(),
    ]);
    $response->assertStatus(200);
    $this->assertDatabaseHas('visits', [
        'id' => $this->visit->id,
        'next_visit_date' => now()->addDays(7)->toDateTimeString(),
    ]);
});
