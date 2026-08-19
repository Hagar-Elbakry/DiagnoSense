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
}