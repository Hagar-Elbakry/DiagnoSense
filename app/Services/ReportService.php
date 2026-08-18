<?php

namespace App\Services;

use App\Helpers\FileSystem;
use App\Models\Patient;
use App\Models\Report;
use Exception;
use Illuminate\Support\Str;

class ReportService
{
    public function getPathsForAI(array $reportsTypes, array $data, Patient $patient, array $pathsForAI): array
    {
        try {
            $reportsToInsert = [];
            foreach ($reportsTypes as $type) {
                if (! empty($data[$type])) {
                    [$pathsForAI, $reportsForType] = $this->processAndStoreFile($data[$type], $type, $patient, $pathsForAI);
                    $reportsToInsert = array_merge($reportsToInsert, $reportsForType);
                }
            }
            Report::insert($reportsToInsert);
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
        $reportsToInsert = [];
        foreach ($data as $file) {
            $fileName = $file->getClientOriginalName();
            $uniqueName = time().'_'.Str::random(5).'.'.$fileName;
            $filePath = FileSystem::storeFile($file, $type, $uniqueName);
            if (! $filePath) {
                throw new Exception("Failed to upload $fileName file to azure blob storage.");
            }
            $mimeType = $file->getMimeType();
            $reportsToInsert[] = [
                'type' => $type,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'patient_id' => $patient->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $pathsForAI[$type][] = $filePath;
        }

        return [$pathsForAI, $reportsToInsert];
    }
}
