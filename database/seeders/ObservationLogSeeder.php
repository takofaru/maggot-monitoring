<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\ObservationLog;
use App\Models\EnvironmentLogs;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ObservationLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cycles = Cycle::orderBy('id')->get();

        if ($cycles->isEmpty()) {
            $this->call(CycleSeeder::class);
            $cycles = Cycle::orderBy('id')->get();
        }

        // Pastikan EnvironmentLog sudah di-seed
        if (EnvironmentLogs::count() === 0) {
            $this->call(EnvironmentLogSeeder::class);
        }

        foreach ($cycles as $cycle) {
            $startDate = Carbon::parse($cycle->start_date);
            $endDate = ($cycle->is_active || empty($cycle->end_date)) ? now() : Carbon::parse($cycle->end_date);
            $totalDays = max(1, (int) $startDate->diffInDays($endDate));

            $feedBase = 3.0;
            $maggotBase = 0.4;

            // Catatan observasi berurutan setiap 2 hari
            for ($day = 0; $day <= $totalDays; $day += 2) {
                $logDate = $startDate->copy()->addDays($day);
                if ($logDate->isAfter(now())) {
                    break;
                }

                // Tentukan nama fase (phase_name) berdasarkan umur siklus
                if ($day <= 5) {
                    $phaseName = 'Penetasan';
                } elseif ($day <= 18) {
                    $phaseName = 'Grow Out';
                } else {
                    $phaseName = 'Prepupa';
                }

                // Cari log lingkungan yang cocok untuk siklus dan tanggal ini
                $envLog = EnvironmentLogs::where('cycle_id', $cycle->id)
                    ->whereDate('timestamp', '<=', $logDate)
                    ->latest('timestamp')
                    ->first();

                if (!$envLog) {
                    $envLog = EnvironmentLogs::where('cycle_id', $cycle->id)->first();
                }

                // Fallback jika belum ada environment log
                if (!$envLog) {
                    $envLog = EnvironmentLogs::create([
                        'cycle_id'    => $cycle->id,
                        'timestamp'   => $logDate->copy()->setHour(12)->toDateTimeString(),
                        'temperature' => 29.00,
                        'humidity'    => 70.00,
                    ]);
                }

                $feedWeight = round($feedBase + ($day * 1.35) + (rand(-4, 4) / 10), 2);
                $maggotWeight = round($maggotBase + ($day * 0.60) + (rand(-2, 2) / 10), 2);

                ObservationLog::create([
                    'cycle_id'        => $cycle->id,
                    'phase_name'      => $phaseName,
                    'environment_log_id' => $envLog->id,
                    'timestamp'       => $logDate->toDateString(),
                    'feed_weight'     => max(0.5, $feedWeight),
                    'maggot_weight'   => max(0.2, $maggotWeight),
                    'created_at'      => $logDate->copy()->setHour(10)->setMinute(0),
                    'updated_at'      => $logDate->copy()->setHour(10)->setMinute(0),
                ]);
            }
        }
    }
}
