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
        // Kumpulan siklus berurutan dihitung mundur dari waktu saat ini
        $cycles = [
            [
                'start_date'    => now()->subDays(180)->toDateString(),
                'end_date'      => now()->subDays(155)->toDateString(),
                'current_phase' => 'Panen',
                'is_active'     => false,
            ],
            [
                'start_date'    => now()->subDays(154)->toDateString(),
                'end_date'      => now()->subDays(130)->toDateString(),
                'current_phase' => 'Panen',
                'is_active'     => false,
            ],
            [
                'start_date'    => now()->subDays(129)->toDateString(),
                'end_date'      => now()->subDays(104)->toDateString(),
                'current_phase' => 'Panen',
                'is_active'     => false,
            ],
            [
                'start_date'    => now()->subDays(103)->toDateString(),
                'end_date'      => now()->subDays(78)->toDateString(),
                'current_phase' => 'Panen',
                'is_active'     => false,
            ],
            [
                'start_date'    => now()->subDays(77)->toDateString(),
                'end_date'      => now()->subDays(52)->toDateString(),
                'current_phase' => 'Panen',
                'is_active'     => false,
            ],
            [
                'start_date'    => now()->subDays(51)->toDateString(),
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
                'end_date'      => null, // Siklus aktif terakhir mempunyai end_date null
                'current_phase' => 'Grow Out',
                'is_active'     => true, // Hanya satu cycle yang aktif (cycle terakhir)
            ],
        ];

        foreach ($cycles as $cycleData) {
            Cycle::create($cycleData);
        }
    }
}
