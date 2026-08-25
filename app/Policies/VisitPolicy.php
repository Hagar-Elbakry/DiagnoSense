<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Auth\Access\Response;

class VisitPolicy
{
    public function view(User $user, Patient $patient): Response
    {
        $doctor = $user->doctor;
        if (! $doctor) {
            return Response::deny('User is not a doctor.');
        }

        return $doctor->patients()->where('patients.id', $patient->id)->exists()
            ? Response::allow()
            : Response::deny('You do not have permission to view visit details for this patient.');
    }

    public function store(User $user, Patient $patient): Response
    {
        $doctor = $user->doctor;
        if (! $doctor) {
            return Response::deny('User is not a doctor.');
        }

        return $doctor->patients()->where('patients.id', $patient->id)->exists()
            ? Response::allow()
            : Response::deny('You do not have permission to create a visit for this patient.');
    }

    public function manage(User $user, Visit $visit): Response
    {
        $doctor = $user->doctor;
        if (! $doctor) {
            return Response::deny('User is not a doctor.');
        }

        return $doctor->visits()->where('visits.id', $visit->id)->exists()
            ? Response::allow()
            : Response::deny('You do not have permission to manage this visit.');
    }
}
