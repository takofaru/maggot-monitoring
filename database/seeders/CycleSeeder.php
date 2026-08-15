<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\EnvironmentLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class CycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Hanya seed first cycle (Siklus 1).
     * Jika alat offline, maka start_date bernilai null (belum dimulai).
     */
    public function run(): void
    {
        // 1. Evaluasi status konektivitas perangkat IoT terkini (Liveness Check)
        $cachedLastSeenStr = Cache::get('device_last_seen');
        $cachedLastSeen = $cachedLastSeenStr ? Carbon::parse($cachedLastSeenStr) : null;
        $latestEnv = EnvironmentLog::latest('timestamp')->latest('id')->first();
        $dbLastSeen = $latestEnv ? Carbon::parse($latestEnv->timestamp ?? $latestEnv->created_at) : null;

        $lastSeen = ($cachedLastSeen && $dbLastSeen)
            ? ($cachedLastSeen->greaterThan($dbLastSeen) ? $cachedLastSeen : $dbLastSeen)
            : ($cachedLastSeen ?? $dbLastSeen);

        $diffInSeconds = $lastSeen ? (int) abs(now()->diffInSeconds($lastSeen, false)) : null;
        $isDeviceOnline = ($diffInSeconds !== null && $diffInSeconds <= 20);

        // Jika alat online, start_date dimulai hari ini. Jika alat offline, start_date belum dimulai (null).
        $startDate = $isDeviceOnline ? now()->toDateString() : null;

        // 2. Buat Siklus Pertama (Siklus 1) jika belum ada
        Cycle::firstOrCreate(
            ['id' => 1],
            [
                'start_date'    => $startDate,
                'end_date'      => null,
                'current_phase' => 'penetasan',
                'is_active'     => true,
            ]
        );
    }
}
