<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->patient->id,
            'name' => $this->name,
            'age' => $this->patient->age ?? 'N/A',
            'status' => $this->patient->status,
            'ai_insight' => $this->patient->latestAiAnalysisValue('ai_insight') ?? 'No analysis available yet',
        ];
    }
}
