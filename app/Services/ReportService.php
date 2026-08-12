<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Str;
use App\Helpers\FileSystem;
use Exception;

class ReportService
{
    public function getPathsForAI(array $reportsTypes, array $data, Patient $patient, array $pathsForAI): array
    {
        try {
            foreach ($reportsTypes as $type) {
                if (! empty($data[$type])) {
                    $pathsForAI = $this->processAndStoreFile($data[$type], $type, $patient, $pathsForAI);
                }
            }
        } catch (Exception $e) {
            foreach ($pathsForAI as $paths) {
                foreach ($paths as $path) {
                    FileSystem::deleteFile($path);
                }
            }
            throw $e;
        }

        return $pathsForAI;
    }

    private function processAndStoreFile(array $data, string $type, Patient $patient, array $pathsForAI): array
    {
        foreach ($data as $file) {
            $fileName = $file->getClientOriginalName();
            $uniqueName = time().'_'.Str::random(5).'.'.$fileName;
            $filePath = FileSystem::storeFile($file, $type, $uniqueName);
            if (! $filePath) {
                throw new Exception("Failed to upload $fileName file to azure blob storage.");
            }
            $mimeType = $file->getMimeType();
            $this->storeReport($patient, $type, $fileName, $filePath, $mimeType);
            $pathsForAI[$type][] = $filePath;
        }

        return $pathsForAI;
    }

    private function storeReport(Patient $patient, string $type, string $fileName, string $filePath, string $mimeType): void
    {
        $patient->reports()->create([
            'type' => $type,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => $mimeType,
        ]);
    }
}