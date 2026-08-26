<?php

use App\Models\AiAnalysisResult;
use App\Models\Patient;
use App\Models\MedicalHistory;

beforeEach(function () {
    $this->doctorUser = createDoctorWithBilling(billingMode: 'pay-per-use', balance: 5000);

    $this->patient1 = Patient::factory()->create(['status' => 'critical']);
    $this->patient2 = Patient::factory()->create(['status' => 'stable']);
    $this->patient3 = Patient::factory()->create(['status' => 'stable']);
    $this->patient3->created_at = now()->subMonth();
    $this->patient3->save();

    $this->doctorUser->doctor->patients()->attach([
        $this->patient1->id,
        $this->patient2->id,
        $this->patient3->id,
    ]);

    $this->visit1 = createVisit($this->doctorUser->doctor, $this->patient1, today()->setTime(9, 0));
    $this->visit2 = createVisit($this->doctorUser->doctor, $this->patient2, today()->setTime(10, 0));
    $this->visit3 = createVisit($this->doctorUser->doctor, $this->patient3, today()->setTime(11, 0));

    MedicalHistory::create([
        'patient_id' => $this->patient1->id,
        'chronic_diseases' => json_encode(['Diabetes', 'Hypertension']),
    ]);

    MedicalHistory::create([
        'patient_id' => $this->patient2->id,
        'chronic_diseases' => json_encode(['Diabetes']),
    ]);

    $this->actingAs($this->doctorUser, 'sanctum');
});

it('returns correct data structure for the status distribution pie chart', function () {
    $response = $this->getJson(route('dashboard.status-distribution'));

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_registered_patients', 3)
        ->assertJsonFragment(['status' => 'critical', 'value' => 1, 'percentage' => 33])
        ->assertJsonFragment(['status' => 'stable', 'value' => 2, 'percentage' => 67])
        ->assertJsonFragment(['status' => 'under review', 'value' => 0, 'percentage' => 0]);
});

it('returns correct aggregated top chronic diseases for the bar chart', function () {
    $response = $this->getJson(route('dashboard.top-diseases'));

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                '*' => ['label', 'value'],
            ],
        ])
        ->assertJsonFragment(['label' => 'Diabetes', 'value' => 2])
        ->assertJsonFragment(['label' => 'Hypertension', 'value' => 1]);
});

it('it returns correct widget data for dashboard', function(){
    AiAnalysisResult::create([
        'patient_id' => $this->patient1->id,
        'status' => 'completed'
    ]);
    
    $this->doctorUser->update([
        'name' => 'Dr. seif',
    ]);

    $response = $this->getJson(route('dashboard.summary'));

        $response->assertJsonFragment(['doctor_name' => 'Dr. seif'])
            ->assertJsonPath('data.widgets.total_patients','3')
            ->assertJsonFragment(['today_appointments' => 3])
            ->assertJsonPath('data.widgets.reports_analyzed','1')
            ->assertJsonPath('data.widgets.monthly_growth.details.growth_rate','100%');
});

it('allows doctor to view today\'s visits successfully', function () {
    $response = $this->getJson(route('dashboard.todayVisits'));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'current_attending' => [
                'id',
                'patient_id',
                'name',
                'age',
                'gender',
                'appointment_time',
                'ai_insight' => [
                    'summary',
                ],
            ],
            'full_queue_list' => [
                "*" => [
                        'id',
                        'patient_id',
                        'name',
                        'age',
                        'gender',
                        'appointment_time',
                        'ai_insight' => [
                            'summary',
                        ],
                        'status_tag',
                ]
            ],
            'remaining_count_label',
        ],
    ]);
});

it('assigns Now status to current patient and Waiting to others', function () {
    $response = $this->getJson(route('dashboard.todayVisits'));
    $response->assertStatus(200);
    $response->assertJsonPath('data.full_queue_list.0.status_tag', 'Now');
    $response->assertJsonPath('data.full_queue_list.1.status_tag', 'Waiting');
    $response->assertJsonPath('data.full_queue_list.2.status_tag', 'Waiting');
});