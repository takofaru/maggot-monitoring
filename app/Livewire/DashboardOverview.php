<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Cycle;
use App\Models\ObservationLog;
use App\Models\EnvironmentLog;
use Carbon\Carbon;

class DashboardOverview extends Component
{
    public function render()
    {
        // 1. Query Siklus Aktif
        $activeCycle = Cycle::latest('updated_at')->first() ?? Cycle::latest('id')->first();
        $cycleNumber = $activeCycle ? ($activeCycle->cycle_number ?? $activeCycle->id) : 1;

        $dayNumber = 1;
        if ($activeCycle && $activeCycle->start_date) {
            $dayNumber = Carbon::parse($activeCycle->start_date)->diffInDays(now()) + 1;
        }

        $currentPhase = $activeCycle->current_phase ?? 'Pertumbuhan / Larva';

        // 2. Query Catatan Observasi / Pemeliharaan Terakhir
        $latestLog = ObservationLog::latest('updated_at')->latest('id')->first();
        
        $lastFeedWeight = $latestLog ? $latestLog->feed_weight : 0;
        $latestMaggotWeight = $latestLog ? $latestLog->maggot_weight : 0;
        
        // Kalkulasi FCR dari input terakhir
        $fcr = ($latestMaggotWeight > 0) ? number_format($lastFeedWeight / $latestMaggotWeight, 2) : '0.00';

        // Mengambil tanggal asli dari database untuk kartu ringkasan
        if ($latestLog) {
            $rawDate = $latestLog->timestamp ?? $latestLog->updated_at ?? $latestLog->created_at;
            $lastUpdateDate = Carbon::parse($rawDate)->translatedFormat('l, d F Y');
        } else {
            $lastUpdateDate = Carbon::now()->translatedFormat('l, d F Y');
        }

        // 3. Query Telemetri Lingkungan (10 Data Terakhir) - Grafik Tetap Utuh
        $envLogsModel = EnvironmentLog::latest('id')->take(10)->get()->reverse();

        $latestEnv = $envLogsModel->last();
        $tempVal = $latestEnv ? $latestEnv->temperature : 0;
        $humidVal = $latestEnv ? $latestEnv->humidity : 0;

        // Pemetaan data array untuk Chart.js (Format jam H:i)
        $chartLabels = [];
        $chartTemp   = [];
        $chartHumid  = [];

        if ($envLogsModel->isNotEmpty()) {
            foreach ($envLogsModel as $log) {
                $chartLabels[] = Carbon::parse($log->timestamp ?? $log->created_at)->format('H:i');
                $chartTemp[]   = (float) $log->temperature;
                $chartHumid[]  = (float) $log->humidity;
            }
        } else {
            $chartLabels = ['08:00', '09:00', '10:00', '11:00', '12:00'];
            $chartTemp   = [0, 0, 0, 0, 0];
            $chartHumid  = [0, 0, 0, 0, 0];
        }

        return view('livewire.dashboard-overview', [
            'cycleNumber'        => $cycleNumber,
            'dayNumber'          => $dayNumber,
            'currentPhase'       => $currentPhase,
            'totalFeed'          => $lastFeedWeight,
            'latestMaggotWeight' => $latestMaggotWeight,
            'fcr'                => $fcr,
            'latestEnv'          => $latestEnv,
            'tempVal'            => $tempVal,
            'humidVal'           => $humidVal,
            'chartLabels'        => $chartLabels,
            'chartTemp'          => $chartTemp,
            'chartHumid'         => $chartHumid,
            'lastUpdateDate'     => $lastUpdateDate,
        ]);
    }
}