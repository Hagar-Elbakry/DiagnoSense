<?php

use App\Models\Task;
use App\Models\Visit;
use App\Notifications\PatientNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->patientUser = createUserWithType('patient', fake()->unique()->safeEmail());
    $this->patient = $this->patientUser->patient;

    $this->doctorUser = createUserWithType('doctor', fake()->unique()->safeEmail(), 'Menna Baligh');
    $this->doctor = $this->doctorUser->doctor;

    $this->visit = Visit::factory()->create([
        'patient_id' => $this->patient->id,
        'doctor_id' => $this->doctor->id,
        'next_visit_date' => now()->addDays(1),
    ]);

    Notification::fake();
});

it('executes the full notification lifecycle smoothly from token registration to delivery and fetching', function () {
    $patchResponse = $this->actingAs($this->patientUser, 'sanctum')->patchJson(route('patients.fcm-token'), [
        'fcm_token' => 'mock_fcm_token_123456_xyz'
    ]);
    $patchResponse->assertStatus(200);

    $this->patientUser->refresh();
    expect($this->patientUser->fcm_token)->toBe('mock_fcm_token_123456_xyz');


    $taskPayload = [
        'title' => 'Drink water',
        'description' => 'Take 3 liters of water daily',
        'notes' => 'After meals',
        'due_date' => now()->addDays(2)->format('Y-m-d'),
        'action' => 'save',
    ];

    $response = $this->actingAs($this->doctorUser, 'sanctum')->postJson(route('visits.tasks.store', ['visit' => $this->visit->id]), $taskPayload);
    $response->assertStatus(200);

    Notification::assertSentTo(
        $this->patientUser,
        PatientNotification::class,
        function ($notification) {
            $data = $notification->toArray($this->patientUser);
            return $data['type'] === 'task';
        }
    );
});

it('returns only the patient medical notifications through the mobile endpoint', function () {
    $this->patientUser->update(['fcm_token' => 'active_token']);

    Task::factory()->create(['visit_id' => $this->visit->id]);

    $this->patientUser->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'App\Notifications\PatientNotification',
        'data' => [
            'type' => 'task',
            'title' => 'Task Title Test',
            'description' => 'Task Description Test',
        ],
    ]);

    $this->patientUser->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'App\Notifications\Credit\CreditAdded',
        'data' => ['message' => 'Credit Added Test'],
    ]);

    $response = $this->actingAs($this->patientUser, 'sanctum')->getJson(route('mobile.notifications'));
    $response->assertStatus(200)->assertJsonPath('success', true);

    $responseData = $response->json('data');
    $notificationsList = isset($responseData['data']) ? $responseData['data'] : $responseData;

    expect($notificationsList)->not->toBeNull();
    expect($notificationsList)->toBeArray();

    $firstNotification = $notificationsList[0] ?? null;
    expect($firstNotification)->not->toBeNull();
    expect($firstNotification)->toHaveKey('id');
    expect($firstNotification['type'])->toBe('TASK');
    expect($firstNotification['title'])->toBe('Task Title Test');
});