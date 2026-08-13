<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\EnvironmentLogs;
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

        foreach ($cycles as $cycle) {
            $startDate = Carbon::parse($cycle->start_date);
            $endDate = ($cycle->is_active || empty($cycle->end_date)) ? now() : Carbon::parse($cycle->end_date);

            if ($cycle->is_active) {
                // Untuk siklus aktif: data berurutan per 2 jam dari awal siklus hingga sekarang
                $current = $startDate->copy()->setHour(8)->setMinute(0);
                while ($current->lessThanOrEqualTo(now())) {
                    $hour = $current->hour;
                    
                    // Suhu: 26.0 - 32.0 °C
                    $tempBase = ($hour >= 11 && $hour <= 15) ? 31.0 : (($hour >= 0 && $hour <= 6) ? 26.5 : 28.5);
                    $temp = round($tempBase + (rand(-12, 12) / 10), 2);

                    // Kelembapan skala 0-100: 60.0% - 85.0%
                    $humidBase = ($hour >= 0 && $hour <= 6) ? 80.0 : (($hour >= 11 && $hour <= 15) ? 65.0 : 72.0);
                    $humid = round($humidBase + (rand(-6, 6)), 2);

                    EnvironmentLogs::create([
                        'cycle_id'    => $cycle->id,
                        'timestamp'   => $current->toDateTimeString(),
                        'temperature' => $temp,
                        'humidity'    => min(99.0, max(10.0, $humid)), // Skala 0 - 100
                        'created_at'  => $current,
                        'updated_at'  => $current,
                    ]);

                    $current->addHours(2);
                }
            } else {
                // Untuk siklus lampau: data telemetri historis berurutan
                $current = $startDate->copy()->setHour(8);
                while ($current->lessThanOrEqualTo($endDate)) {
                    for ($h = 8; $h <= 20; $h += 4) {
                        $logTime = $current->copy()->setHour($h)->setMinute(rand(0, 59));
                        $temp = round(28.0 + (rand(-15, 15) / 10), 2);
                        $humid = round(70.0 + (rand(-10, 10)), 2); // Skala 0 - 100

                        EnvironmentLogs::create([
                            'cycle_id'    => $cycle->id,
                            'timestamp'   => $logTime->toDateTimeString(),
                            'temperature' => $temp,
                            'humidity'    => min(99.0, max(10.0, $humid)), // Skala 0 - 100
                            'created_at'  => $logTime,
                            'updated_at'  => $logTime,
                        ]);
                    }
                    $current->addDays(2);
                }
            }
        }
    }
}
