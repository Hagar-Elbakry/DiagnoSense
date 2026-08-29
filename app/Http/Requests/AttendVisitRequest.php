<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class AttendVisitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(Request $request): bool
    {
        $doctor = $request->user()->doctor;
        $visit = $this->route('visit');
        return $doctor->visits()->where('visits.id', $visit->id)->exists();
    }
}
