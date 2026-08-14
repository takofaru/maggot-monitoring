<?php

namespace Database\Seeders;

use App\Models\PhaseSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pengaturan Fase Budidaya (Phase Settings - Skala Kelembapan 0-100)
        $phaseSettings = [
            [
                'order'        => 1,
                'phase_name'   => 'Penetasan',
                'temp_bottom'  => 27.00,
                'temp_top'     => 30.00,
                'humid_bottom' => 60.00,
                'humid_top'    => 80.00,
            ],
            [
                'order'        => 2,
                'phase_name'   => 'Grow Out',
                'temp_bottom'  => 26.00,
                'temp_top'     => 32.00,
                'humid_bottom' => 60.00,
                'humid_top'    => 85.00,
            ],
            [
                'order'        => 3,
                'phase_name'   => 'Prepupa',
                'temp_bottom'  => 25.00,
                'temp_top'     => 29.00,
                'humid_bottom' => 50.00,
                'humid_top'    => 70.00,
            ],
        ];

        foreach ($phaseSettings as $setting) {
            PhaseSetting::firstOrCreate(
                ['phase_name' => $setting['phase_name']],
                $setting
            );
        }

        // 2. Panggil Seluruh Seeder (User, Cycle, EnvironmentLog, ObservationLog)
        $this->call([
            UserSeeder::class,
            CycleSeeder::class,
            EnvironmentLogSeeder::class,
            ObservationLogSeeder::class,
        ]);
    }
}