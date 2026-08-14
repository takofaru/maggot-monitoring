<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\ObservationLog;
use App\Models\EnvironmentLog;
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
        if (EnvironmentLog::count() === 0) {
            $this->call(EnvironmentLogSeeder::class);
        }

        $records = [];
        $batchSize = 100;

        foreach ($cycles as $cycle) {
            $startDate = Carbon::parse($cycle->start_date);
            $endDate = ($cycle->is_active || empty($cycle->end_date)) ? now() : Carbon::parse($cycle->end_date);
            $totalDays = max(1, (int) $startDate->diffInDays($endDate));

            $feedBase = 2.5;
            $maggotBase = 0.3;

            // Catatan observasi harian untuk setiap hari pada setiap siklus
            for ($day = 0; $day <= $totalDays; $day++) {
                $logDate = $startDate->copy()->addDays($day);
                if ($logDate->isAfter(now())) {
                    break;
                }

                // Tentukan nama fase berdasarkan umur hari siklus (enum: penetasan, pembesaran, prepupa)
                if ($day <= 5) {
                    $phaseName = 'penetasan';
                } elseif ($day <= 18) {
                    $phaseName = 'pembesaran';
                } else {
                    $phaseName = 'prepupa';
                }

                // Cari log lingkungan yang paling dekat dengan tanggal dan siklus ini
                $envLog = EnvironmentLog::where('cycle_id', $cycle->id)
                    ->whereDate('timestamp', '<=', $logDate)
                    ->latest('timestamp')
                    ->first();

                if (!$envLog) {
                    $envLog = EnvironmentLog::where('cycle_id', $cycle->id)->first();
                }

                // Pertumbuhan bobot maggot dan pakan yang realistis & bertahap
                $feedWeight = round($feedBase + ($day * 1.25) + (rand(-3, 3) / 10), 2);
                $maggotWeight = round($maggotBase + ($day * 0.45) + (rand(-2, 2) / 10), 2);

                $createdAt = $logDate->copy()->setHour(9)->setMinute(rand(0, 59))->toDateTimeString();

                $records[] = [
                    'cycle_id'           => $cycle->id,
                    'phase_name'         => $phaseName,
                    'environment_log_id' => $envLog?->id ?? 1,
                    'timestamp'          => $logDate->toDateString(),
                    'feed_weight'        => max(0.5, $feedWeight),
                    'maggot_weight'      => max(0.2, $maggotWeight),
                    'created_at'         => $createdAt,
                    'updated_at'         => $createdAt,
                ];

                if (count($records) >= $batchSize) {
                    ObservationLog::insert($records);
                    $records = [];
                }
            }
        }

        if (!empty($records)) {
            ObservationLog::insert($records);
        }
    }
}
