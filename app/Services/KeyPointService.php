<?php

namespace App\Services;

use App\Models\KeyPoint;
use App\Models\Patient;
use Illuminate\Support\Collection;
use App\Models\AiAnalysisResult;
use App\Helpers\FileSystem;
use App\Services\AiAnalysisService;
use Exception;

class KeyPointService
{
    public function __construct(
        protected AiAnalysisService $aiAnalysisService
    ){}

    public function getPatientKeyInfo(Patient $patient): array
    {
        $allAnalyses = $this->fetchAnalysesWithKeyPoints($patient);
        $latestAnalysis = $allAnalyses->first();
        $analysesWithKeyPoints = $this->filterAnalysesWithKeyPoint($allAnalyses);

        $hasCurrentData = $latestAnalysis?->keyPoints->isNotEmpty() ?? false;
        $hasOldData = $analysesWithKeyPoints
            ->where('id', '!=', $latestAnalysis?->id)
            ->isNotEmpty();
        $isStillProcessing = $latestAnalysis?->status === 'processing';

        $ocrFiles = $this->extractOcrTemporaryUrls($analysesWithKeyPoints);
        $allKeyPoints = $this->extractAndSortKeyPoints($analysesWithKeyPoints);

        return [
            'message' => $this->aiAnalysisService->determineStatusMessage($hasCurrentData, $hasOldData, $isStillProcessing, 'key points',$latestAnalysis?->status ?? 'completed'),
            'data' => [
                'still_processing' => $isStillProcessing && ! $hasCurrentData,
                'ocr_files' => $ocrFiles,
                'key_points' => $this->groupKeyPointsByPriority($allKeyPoints),
            ],
        ];
    }

    public function storeManualNote(Patient $patient, array $data): KeyPoint
    {
        $latestAnalysis = $patient->latestAiAnalysisResult;
        if (! $latestAnalysis) {
            throw new Exception('Cannot add note: No completed analysis found for this patient.', 422);
        }

        return $latestAnalysis->keyPoints()->create([
            'insight' => $data['insight'],
            'priority' => $data['priority'],
            'is_ai_generated' => false,
        ]);
    }

    public function updateKeyPoint(KeyPoint $key_point, array $data): void
    {
        $key_point->update(['insight' => $data['insight']]);
    }

    public function deleteKeyPoint(KeyPoint $key_point): void
    {
        $key_point->delete();
    }

    private function fetchAnalysesWithKeyPoints(Patient $patient): Collection
    {
        return $patient->aiAnalysisResults()->with('keyPoints')->latest()->get();
    }

    private function filterAnalysesWithKeyPoint(Collection $analyses): Collection
    {
        return $analyses->filter(fn ($analysis) => $analysis->keyPoints->isNotEmpty());
    }

    private function extractOcrTemporaryUrls(Collection $analysesWithData): array
    {
        return $analysesWithData
            ->filter(fn ($analysis) => $analysis->ocr_file_path)
            ->map(fn ($analysis) => FileSystem::getTempUrl($analysis->ocr_file_path))
            ->values()
            ->all();
    }

    private function extractAndSortKeyPoints(Collection $analysesWithData): Collection
    {
        return $analysesWithData->flatMap->keyPoints->sortByDesc('created_at');
    }

    private function groupKeyPointsByPriority(Collection $allKeyPoints): array
    {
        return [
        'high' => $allKeyPoints->where('priority', 'high')->values(),
        'medium' => $allKeyPoints->where('priority', 'medium')->values(),
        'low' => $allKeyPoints->where('priority', 'low')->values(),
    ];
    }
}