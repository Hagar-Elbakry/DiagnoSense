<?php

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Patient;

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

    private function getPatientStatusDistribution(Doctor $doctor): Collection
    {
        return $doctor->patients()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
    }
}