<?php

namespace App\Jobs;

use App\Models\AiAnalysisResult;
use App\Models\Patient;
use App\Models\PatientLabResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Exception;

class ComparativeAnalysis implements ShouldQueue
{
    use Dispatchable , InteractsWithQueue , Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(
        public Patient $patient,
        public AiAnalysisResult $analysis
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {

            $response = Http::timeout($this->timeout)->post(config('services.ai.url').'comparative', [
                'patient_id' => $this->patient->id,
            ]);

            if ($response->failed()) {
                throw new Exception('AI Server error: '.$response->status());
            }

            $labResults = $response->json()['data']['lab_results'] ?? [];
            if (empty($labResults)) {
                throw new Exception('No lab results found in AI response.');
            }

            $dataToInsert = $this->getDataToInsert($labResults);

            DB::transaction(function () use ($dataToInsert) {
                PatientLabResult::insert($dataToInsert);
                $this->analysis->update(['status' => 'completed']);
            });
    }

    private function getDataToInsert(mixed $labResults): array
    {
        $dataToInsert = collect($labResults)->map(function ($result) {
            return [
                'patient_id' => $this->patient->id,
                'ai_analysis_result_id' => $this->analysis->id,
                'category' => $result['category'],
                'standard_name' => $result['standard_name'],
                'numeric_value' => $result['numeric_value'],
                'unit' => $result['unit'] ?? '',
                'status' => $result['status'],
                'created_at' => now(),
            ];
        })->toArray();
        
        return $dataToInsert;
    }
}
