<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\PhaseSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Pengguna (Admin & Siswa)
        User::firstOrCreate(
            ['username' => 'admin2'],
            [
                'full_name'     => 'Admin Dua',
                'password_hash' => Hash::make('password123'),
                'role'          => 'admin',
            ]
        );

        User::firstOrCreate(
            ['username' => 'siswa2'],
            [
                'full_name'     => 'Siswa Dua',
                'password_hash' => Hash::make('password123'),
                'role'          => 'user',
            ]
        );

        // 2. Pengaturan Fase Budidaya (Phase Settings - Skala Kelembapan 0-100)
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

        // 3. Panggil Seeder Cycle, EnvironmentLog, dan ObservationLog
        $this->call([
            CycleSeeder::class,
            EnvironmentLogSeeder::class,
            ObservationLogSeeder::class,
        ]);
    }
}