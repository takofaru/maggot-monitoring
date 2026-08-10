<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Cycle;
use App\Models\EnvironmentLogs;
use App\Models\ObservationLog;
use Carbon\Carbon;

#[Layout('components.layouts.app')]
class DashboardOverview extends Component
{
    public function render()
    {
        // 1. Ambil siklus aktif (atau siklus pertama sebagai fallback)
        $activeCycle = Cycle::where('is_active', true)->first() ?? Cycle::first();

        $cycleNumber = $activeCycle ? $activeCycle->id : 1;
        
        // Perhitungan Hari ke- n berdasarkan tanggal mulai siklus
        $dayNumber = 1;
        if ($activeCycle && $activeCycle->start_date) {
            $startDate = Carbon::parse($activeCycle->start_date);
            $dayNumber = max(1, (int) $startDate->diffInDays(now()) + 1);
        }

        // Tentukan Fase Budidaya berdasarkan hari
        $currentPhase = 'Fase Penetasan / Biopond';
        if ($dayNumber > 5 && $dayNumber <= 15) {
            $currentPhase = 'Fase Pembesaran Maggot';
        } elseif ($dayNumber > 15) {
            $currentPhase = 'Fase Prepupa / Panen';
        }

        // 2. Perhitungan Akumulasi Pakan & Berat Maggot dari ObservationLog
        $totalFeed = 0;
        $latestMaggotWeight = 0;
        $fcr = '0.0';

        if ($activeCycle) {
            $totalFeed = ObservationLog::where('cycle_id', $activeCycle->id)->sum('feed_weight');
            
            $latestObs = ObservationLog::where('cycle_id', $activeCycle->id)
                ->latest('timestamp')
                ->first();

            if ($latestObs) {
                $latestMaggotWeight = $latestObs->maggot_weight;
            }

            // Perhitungan FCR (Feed Conversion Ratio) = Total Pakan / Berat Maggot Terakhir
            if ($latestMaggotWeight > 0) {
                $fcrVal = $totalFeed / $latestMaggotWeight;
                $fcr = number_format($fcrVal, 2);
            }
        }

        // 3. Ambil Log Sensor Lingkungan (Suhu & Kelembapan) dari SQLite
        $envLogs = collect();
        if ($activeCycle) {
            $envLogs = EnvironmentLogs::where('cycle_id', $activeCycle->id)
                ->latest('id')
                ->take(15)
                ->get()
                ->reverse(); // Urutkan dari yang lama ke baru untuk grafik
        }

        return view('livewire.dashboard-overview', [
            'activeCycle'        => $activeCycle,
            'cycleNumber'        => $cycleNumber,
            'dayNumber'          => $dayNumber,
            'currentPhase'       => $currentPhase,
            'totalFeed'          => $totalFeed,
            'latestMaggotWeight' => $latestMaggotWeight,
            'fcr'                => $fcr,
            'envLogs'            => $envLogs,
        ]);
    }
}