<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\EnvironmentLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EnvironmentLogSeeder extends Seeder
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

        $records = [];
        $batchSize = 200;

        foreach ($cycles as $cycle) {
            $startDate = Carbon::parse($cycle->start_date);
            $endDate = ($cycle->is_active || empty($cycle->end_date)) ? now() : Carbon::parse($cycle->end_date);

            if ($cycle->is_active) {
                // Untuk siklus aktif: data per jam dari awal siklus hingga jam saat ini
                $current = $startDate->copy()->setHour(0)->setMinute(0)->setSecond(0);
                while ($current->lessThanOrEqualTo(now())) {
                    $hour = $current->hour;

                    // Dinamika suhu harian realistis (25.5°C malam s/d 32.5°C siang)
                    $tempBase = ($hour >= 11 && $hour <= 15) ? 31.2 : (($hour >= 0 && $hour <= 6) ? 26.3 : 28.5);
                    $temp = round($tempBase + (rand(-10, 10) / 10), 2);

                    // Dinamika kelembapan skala 0-100 (60% siang s/d 88% malam/pagi)
                    $humidBase = ($hour >= 0 && $hour <= 6) ? 82.0 : (($hour >= 11 && $hour <= 15) ? 64.0 : 73.0);
                    $humid = round($humidBase + (rand(-5, 5)), 2);

                    $records[] = [
                        'cycle_id'    => $cycle->id,
                        'timestamp'   => $current->toDateTimeString(),
                        'temperature' => $temp,
                        'humidity'    => min(99.0, max(10.0, $humid)),
                        'created_at'  => $current->toDateTimeString(),
                        'updated_at'  => $current->toDateTimeString(),
                    ];

                    if (count($records) >= $batchSize) {
                        EnvironmentLog::insert($records);
                        $records = [];
                    }

                    $current->addHour();
                }
            } else {
                // Untuk siklus lampau: 6 data per hari (04:00, 08:00, 12:00, 15:00, 18:00, 22:00) sepanjang siklus
                $current = $startDate->copy();
                while ($current->lessThanOrEqualTo($endDate)) {
                    $hours = [4, 8, 12, 15, 18, 22];
                    foreach ($hours as $h) {
                        $logTime = $current->copy()->setHour($h)->setMinute(rand(0, 59))->setSecond(0);
                        if ($logTime->isAfter($endDate->copy()->endOfDay())) {
                            continue;
                        }

                        $tempBase = ($h >= 11 && $h <= 15) ? 31.0 : (($h <= 6) ? 26.0 : 28.2);
                        $temp = round($tempBase + (rand(-12, 12) / 10), 2);

                        $humidBase = ($h <= 6) ? 83.0 : (($h >= 11 && $h <= 15) ? 63.0 : 72.0);
                        $humid = round($humidBase + (rand(-6, 6)), 2);

                        $records[] = [
                            'cycle_id'    => $cycle->id,
                            'timestamp'   => $logTime->toDateTimeString(),
                            'temperature' => $temp,
                            'humidity'    => min(99.0, max(10.0, $humid)),
                            'created_at'  => $logTime->toDateTimeString(),
                            'updated_at'  => $logTime->toDateTimeString(),
                        ];

                        if (count($records) >= $batchSize) {
                            EnvironmentLog::insert($records);
                            $records = [];
                        }
                    }
                    $current->addDay();
                }
            }
        }

        if (!empty($records)) {
            EnvironmentLog::insert($records);
        }
    }
}
