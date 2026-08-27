<?php

namespace App\Services;

use App\Models\User;

class DoctorService
{
    public function getDoctorProfileData(User $user): User
    {
        $user['specialization'] = $user->doctor->specialization;

        return $user;
    }
}