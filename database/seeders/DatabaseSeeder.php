<?php

namespace Database\Seeders;

use App\Models\PhaseSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for deployment.
     * Hanya seed Phase Settings, User (Admin), dan First Cycle (Siklus 1).
     */
    public function run(): void
    {
        // 1. Pengaturan Default Batas Fase Budidaya (Phase Settings)
        $phaseSettings = [
            [
                'order'        => 1,
                'phase_name'   => 'penetasan',
                'temp_bottom'  => 26.50,
                'temp_top'     => 32.00,
                'humid_bottom' => 60.00,
                'humid_top'    => 85.00,
            ],
            [
                'order'        => 2,
                'phase_name'   => 'pembesaran',
                'temp_bottom'  => 27.00,
                'temp_top'     => 33.00,
                'humid_bottom' => 60.00,
                'humid_top'    => 80.00,
            ],
            [
                'order'        => 3,
                'phase_name'   => 'prepupa',
                'temp_bottom'  => 25.00,
                'temp_top'     => 30.00,
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

        // 2. Panggil Seeder Inti Deployment (User Admin dan First Cycle)
        $this->call([
            UserSeeder::class,
            CycleSeeder::class,
        ]);
    }
}