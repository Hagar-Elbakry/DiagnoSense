<?php

namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class DeletePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(Request $request): bool
    {
        $patient = $this->route('patient');

        return $patient->doctors()
            ->where('doctor_id', $request->user()->doctor->id)
            ->exists();
    }
}
