<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Visit;
use App\Helpers\PushNotification;

class VisitService
{
    public function store(array $data, Patient $patient, Doctor $doctor): Visit
    {
        $status = $data['action'] == 'save' ? 'completed' : 'draft';
        $nextVisitDate = $data['next_visit_date'] ?? null;
        $visit = $doctor->visits()->create([
            'patient_id' => $patient->id,
            'next_visit_date' => $nextVisitDate,
            'status' => $status,
        ]);

        if ($visit->next_visit_date) {
            PushNotification::sendToPatient(
            patient: $patient,
            type: 'visit',
            title: __('Upcoming Appointment Scheduled'),
            body: __('Your next visit is scheduled on: :date', ['date' => $visit->next_visit_date?->format('Y-m-d h:i A')])
            );
        }

        return $visit->load('doctor.user');
    }
}