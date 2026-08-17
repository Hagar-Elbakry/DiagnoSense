<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class GetPatientDataForUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(Request $request): bool
    {
        $currentDoctor = $request->user()->doctor;
        if (! $currentDoctor) {
            return false;
        }
        $patient = $this->route('patient');

        return $currentDoctor->patients()->where('patients.id', $patient->id)->exists();
    }
}
