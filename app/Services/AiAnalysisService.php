<?php

namespace App\Services;

use App\Models\Patient;

class AiAnalysisService 
{
    public function getPatientOverview(Patient $patient)
    {
        return $patient->load([
            'user',
            'medicalHistory',
        ]);
    }
}