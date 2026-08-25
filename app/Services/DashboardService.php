<?php

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Patient;
use App\Models\MedicalHistory;

class DashboardService
{
    public function getPatientStatusChartData(Doctor $doctor): array
    {
        $distribution = $this->getPatientStatusDistribution($doctor);
        $totalPatients = (int) $distribution->sum();
        $pieChartData = collect(Patient::getStatuses())->map(function ($status) use ($distribution, $totalPatients) {
        $count = (int) ($distribution[$status->value] ?? 0);
            return [
                'status' => $status->value,
                'value' => $count,
                'percentage' => $totalPatients > 0 ? round(($count / $totalPatients) * 100) : 0
            ];
        })->values()->all();

        return [
            'total_registered_patients' => $totalPatients,
            'pie_chart_data' => $pieChartData,
        ];      
    }

    public function getTopChronicDiseases(Doctor $doctor): Collection
    {
        $histories = MedicalHistory::whereHas('patient.doctors', function ($query) use ($doctor) {
            $query->where('doctors.id', $doctor->id);
        })
            ->whereNotNull('chronic_diseases')
            ->pluck('chronic_diseases');

        return collect($histories)
            ->flatMap(function ($diseases) {
                return is_string($diseases) ? json_decode($diseases, true) : (array) $diseases;
            })
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5);
    }

    private function getPatientStatusDistribution(Doctor $doctor): Collection
    {
        return $doctor->patients()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
    }
}