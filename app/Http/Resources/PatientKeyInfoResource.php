<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\KeyPointResource;

class PatientKeyInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'still_processing' => $this->resource['still_processing'],
            'ocr_files' => $this->resource['ocr_files'],
            'key_points' => [
                'high' => KeyPointResource::collection(
                    $this->resource['key_points']['high']
                ),
                'medium' => KeyPointResource::collection(
                    $this->resource['key_points']['medium']
                ),
                'low' => KeyPointResource::collection(
                    $this->resource['key_points']['low']
                ),
            ],
        ];
    }
}
