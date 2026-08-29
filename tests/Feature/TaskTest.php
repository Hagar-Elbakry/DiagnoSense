<?php

use App\Models\Visit;
use App\Models\Task;

beforeEach(function () {
    $this->user = createUserWithType('doctor', fake()->unique()->safeEmail());
    $this->patient = createUserWithType('patient', fake()->unique()->safeEmail());
    $this->user->doctor->patients()->attach($this->patient->patient->id);
    $this->visit = createVisit($this->user->doctor, $this->patient->patient);

    $this->task = [
        'title' => 'Task 1',
        'description' => 'Task description',
        'action' => 'save',
    ];

    $this->task1 = Task::create([
        'title' => 'Task 1',
        'description' => 'Task description',
        'visit_id' => $this->visit->id,
    ]);
    $this->task2 = Task::create([
        'title' => 'Task 2',
        'description' => 'Task description',
        'visit_id' => $this->visit->id,
    ]);
});

it('allows patient to view their tasks', function () {
    $response = $this->actingAs($this->patient, 'sanctum')->getJson(route('tasks.index'));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            '*' => [
                'id',
                'title',
                'description',
                'notes',
                'is_completed',
                'action' ,
                'due_date',
                'doctor_name',
                'created_at',
                'updated_at',
            ],
        ],
    ]);
});

it('allows doctor to add task to visit successfully', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson(route('visits.tasks.store', ['visit' => $this->visit->id]), $this->task);
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
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
    ]);
    $this->assertDatabaseHas('tasks', [
        'title' => $this->task['title'],
        'visit_id' => $this->visit->id,
    ]);
    $this->assertDatabaseHas('visits', [
        'id' => $this->visit->id,
        'status' => 'completed',
    ]);
});

it('prevents doctor from adding task to unassigned patient', function () {
    $otherDoctor = createUserWithType('doctor', fake()->unique()->safeEmail());
    $response = $this->actingAs($otherDoctor, 'sanctum')->post(route('visits.tasks.store', ['visit' => $this->visit->id]), $this->task);
    $response->assertStatus(403);
});

it('denies task creation if next visit date is missing', function () {
    $otherVisit = Visit::create([
        'next_visit_date' => null,
        'patient_id' => $this->patient->patient->id,
        'doctor_id' => $this->user->doctor->id,
        'status' => 'draft',
    ]);
    $response = $this->actingAs($this->user, 'sanctum')->postJson(route('visits.tasks.store', ['visit' => $otherVisit->id]), $this->task);
    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Next visit date is required for tasks.',
    ]);
});


it('allows patient to view task details', function () {
    $response = $this->actingAs($this->patient, 'sanctum')->getJson(route('tasks.show', ['task' => $this->task1->id]));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
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
    ]);
});

it('prevents patient from viewing task details that are not assigned to them', function () {
    $otherPatient = createUserWithType('patient', fake()->unique()->safeEmail());
    $response = $this->actingAs($otherPatient, 'sanctum')->getJson(route('tasks.show', ['task' => $this->task1->id]));
    $response->assertStatus(403);
});

it('allows patient to mark task as completed', function () {
    $response = $this->actingAs($this->patient, 'sanctum')->patchJson(route('tasks.toggle-completion', ['task' => $this->task1->id]));
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Task marked as completed',
    ]);
    $this->assertDatabaseHas('tasks', [
        'id' => $this->task1->id,
        'is_completed' => true,
    ]);
});

it('allows doctor to delete task successfully', function () {
    $task = $this->visit->tasks()->create($this->task);
    $response = $this->actingAs($this->user, 'sanctum')->deleteJson(route('tasks.destroy', ['task' => $task->id]));
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Task deleted successfully',
        'data' => null,
    ]);
    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});
