<?php

namespace Database\Seeders;

use App\Models\Cycle;
use Illuminate\Database\Seeder;

class CycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hitung tanggal mundur berurutan dari sekarang
        $cycles = [
            [
                'start_date'    => now()->subDays(50)->toDateString(),
                'end_date'      => now()->subDays(26)->toDateString(),
                'current_phase' => 'Panen',
                'is_active'     => false,
            ],
            [
                'start_date'    => now()->subDays(25)->toDateString(),
                'end_date'      => now()->subDays(9)->toDateString(),
                'current_phase' => 'Prepupa',
                'is_active'     => false,
            ],
            [
                'start_date'    => now()->subDays(8)->toDateString(),
                'end_date'      => null, // Cycle aktif terakhir mempunyai end_date null
                'current_phase' => 'Grow Out',
                'is_active'     => true, // Hanya satu cycle yang aktif (cycle terakhir)
            ],
        ];

        foreach ($cycles as $cycleData) {
            Cycle::create($cycleData);
        }
    }
}
