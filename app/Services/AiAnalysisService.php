<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\AiAnalysisResult;
use Illuminate\Support\Collection;

class AiAnalysisService 
{
    public function getPatientOverview(Patient $patient): Patient
    {
        return $patient->load([
            'user',
            'medicalHistory',
        ]);
    }

    public function getPatientDecisionSupport(Patient $patient): array
    {
        $latestAnalysis = $this->fetchLatestAnalysisWithDecisions($patient);
        $oldAnalysis = $this->fetchPreviousCompletedAnalysis($patient, $latestAnalysis?->id);

        $isStillProcessing = $latestAnalysis?->status === 'processing';
        $hasCurrentDecisions = $this->hasDecisionSupports($latestAnalysis);
        $hasOldDecisions = $this->hasDecisionSupports($oldAnalysis);

        $decisionsToReturn = $this->resolveDecisionsToReturn(
            $latestAnalysis,
            $oldAnalysis
    );

        return [
            'message' => $this->determineStatusMessage($hasCurrentDecisions, $hasOldDecisions, $isStillProcessing, 'decision support',$latestAnalysis?->status ?? 'completed'),
            'data' => [
                'still_processing' => $isStillProcessing && ! $hasCurrentDecisions,
                'decisions' => $decisionsToReturn,
            ],
        ];
    }

    public function getPatientComparativeAnalysis(Patient $patient): array
    {
        $latestAnalysis = $patient->latestAiAnalysisResult;
        $isProcessing = $latestAnalysis?->status === 'processing';
        $allResults = $patient->labResults()->orderBy('created_at')->get();

        if ($allResults->isEmpty() && !$isProcessing) {
            return [
                'message' => 'No comparative analysis data available for this patient.',
                'data' => [
                    'still_processing' => false,
                    'analysis' => [],
                ],
            ];
        }

        $analysisResponse = $this->formatComparativeData($allResults);

        $message = 'Comparative analysis retrieved successfully.';
        if ($latestAnalysis?->status === 'failed') {
            $message = 'Note: The AI failed to extract data from the latest reports. Showing historical data only.';
        }

        return [
            'message' => $message,
            'data' => [
                'still_processing' => $isProcessing,
                'analysis' => $analysisResponse,
            ],
        ];
    }

    private function fetchLatestAnalysisWithDecisions(Patient $patient): ?AiAnalysisResult
    {
        return $patient->latestAiAnalysisResult()->with('decisionSupports')->first();
    }

    private function fetchPreviousCompletedAnalysis(Patient $patient, ?int $excludeId): ?AiAnalysisResult
    {
        if (! $excludeId) {
            return null;
        }

        return $patient->aiAnalysisResults()
            ->with('decisionSupports')
            ->where('id', '!=', $excludeId)
            ->where('status', 'completed')
            ->latest()
            ->first();
    }

    private function resolveDecisionsToReturn(
        ?AiAnalysisResult $latest,
        ?AiAnalysisResult $old
    ): Collection {
        if ($latest?->decisionSupports->isNotEmpty()) {
            return $latest->decisionSupports;
        }

        if ($old?->decisionSupports->isNotEmpty()) {
            return $old->decisionSupports;
        }

        return collect();
    }

    private function determineStatusMessage(bool $hasCurrentData, bool $hasOldData, bool $isStillProcessing, string $label,string $status): string
    {
        if ($status === 'failed') {
            return $hasOldData
                ? "Note: The AI failed to extract latest {$label}. Showing historical data only."
                : "No {$label} found. The AI analysis failed for this patient.";
        }
        if ($isStillProcessing && $hasCurrentData) {
            return "{$label} retrieved successfully but comparative analysis is still running.";
        }
        if ($isStillProcessing && $hasOldData) {
            return "Showing old {$label}. Some files are still being processed.";
        }
        if ($isStillProcessing) {
            return "AI analysis for {$label} is still running.";
        }

        return $hasOldData || $hasCurrentData ? "{$label} retrieved successfully." : "No {$label} found for this patient.";
    }

    private function hasDecisionSupports(?AiAnalysisResult $analysis): bool
    {
        return $analysis?->decisionSupports->isNotEmpty() ?? false;
    }

    private function formatComparativeData(Collection $labResults): Collection
    {
        return $labResults
        ->groupBy('standard_name')
        ->map(fn ($testResults, $testName) =>
            $this->formatTestComparison($testResults, $testName)
        )
        ->values();
    }

    private function formatTestComparison(
    Collection $testResults,
    string $testName
    ): array {
        $count = $testResults->count();
        $currentRecord = $testResults->last();
        $hasPrevious = $count > 1;

        $previousRecord = $hasPrevious
            ? $testResults->get($count - 2)
            : $currentRecord;

        $currentValue = (float) $currentRecord->numeric_value;
        $previousValue = (float) $previousRecord->numeric_value;

        $changeValue = round($currentValue - $previousValue, 2);

        $percentage = $previousValue != 0
            ? round(($changeValue / $previousValue) * 100, 1)
            : 0;

        return [
            'test_name' => $testName,
            'category' => $currentRecord->category,
            'unit' => $currentRecord->unit,
            'comparison' => [
                'current_value' => $currentValue,
                'previous_value' => $hasPrevious ? $previousValue : 'Initial',
                'change_value' => $changeValue,
                'change_percentage' => $percentage,
                'trend' => $this->calculateTrend($currentValue, $previousValue),
                'status' => $currentRecord->status,
            ],
            'all_points' => $this->formatHistoricalPoints($testResults),
        ];
    }

    private function calculateTrend(float $current, float $previous): string
    {
        if ($current > $previous) {
            return 'up';
        }
        if ($current < $previous) {
            return 'down';
        }

        return 'stable';
    }

    private function formatHistoricalPoints(Collection $testResults): Collection
    {
        return $testResults->map(fn ($item, $index) => [
            'visit_label' => 'Visit #'.($index + 1),
            'value' => (float) $item->numeric_value,
            'status' => $item->status,
            'date' => $item->created_at->format('Y-m-d'),
        ])->values();
    }

}