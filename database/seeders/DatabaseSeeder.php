<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Cycle;
use App\Models\ObservationLog;
use App\Models\EnvironmentLogs;
use App\Models\PhaseSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
                User::create([
            'full_name'     => 'Admin Dua',
            'username'      => 'admin2',
            'password_hash' => Hash::make('password123'),
            'role'          => 'admin',
        ]);

        // Akun User/Siswa Tambahan
        User::create([
            'full_name'     => 'Siswa Dua',
            'username'      => 'siswa2',
            'password_hash' => Hash::make('password123'),
            'role'          => 'user',
        ]);

        // 2. Phase Settings (Tabel phase_settings: order, phase_name, temp_bottom, temp_top, humid_bottom, humid_top)
        PhaseSetting::create([
            'order'        => 1,
            'phase_name'   => 'Penetasan',
            'temp_bottom'  => 27.00,
            'temp_top'     => 30.00,
            'humid_bottom' => 0.60,
            'humid_top'    => 0.80,
        ]);

        PhaseSetting::create([
            'order'        => 2,
            'phase_name'   => 'Grow Out',
            'temp_bottom'  => 26.00,
            'temp_top'     => 32.00,
            'humid_bottom' => 0.60,
            'humid_top'    => 0.85,
        ]);

        PhaseSetting::create([
            'order'        => 3,
            'phase_name'   => 'Prepupa',
            'temp_bottom'  => 25.00,
            'temp_top'     => 29.00,
            'humid_bottom' => 0.50,
            'humid_top'    => 0.70,
        ]);

        // 3. Siklus (Tabel cycles: start_date, end_date, current_phase, is_active)
        $activeCycle = Cycle::create([
            'start_date'    => now()->subDays(10)->toDateString(),
            'end_date'      => now()->addDays(20)->toDateString(),
            'current_phase' => 'Grow Out',
            'is_active'     => true,
        ]);

        Cycle::create([
            'start_date'    => now()->subDays(40)->toDateString(),
            'end_date'      => now()->subDays(10)->toDateString(),
            'current_phase' => 'Panen',
            'is_active'     => false,
        ]);

        // 4. Catatan Pemeliharaan (Tabel observation_logs: cycle_id, timestamp, feed_weight, maggot_weight)
        ObservationLog::create([
            'cycle_id'      => $activeCycle->id,
            'timestamp'     => now()->subDays(2)->toDateString(),
            'feed_weight'   => 10.00,
            'maggot_weight' => 2.50,
        ]);

        ObservationLog::create([
            'cycle_id'      => $activeCycle->id,
            'timestamp'     => now()->toDateString(),
            'feed_weight'   => 15.50,
            'maggot_weight' => 5.80,
        ]);

        // 5. Log Lingkungan (Tabel environment_logs: cycle_id, timestamp, temperature, humidity)
        EnvironmentLogs::create([
            'cycle_id'    => $activeCycle->id,
            'timestamp'   => now(),
            'temperature' => 28.50,
            'humidity'    => 0.70, // 0.70 setara 70% untuk format decimal(3,2)
        ]);
    }
}