<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ObservationLog;
use App\Models\Cycle;
use App\Models\PhaseSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * Catat aktivitas penambahan/pembaruan log observasi beserta nama petugas pembuat.
     */
    public static function logObservation(ObservationLog $obs, ?User $user = null, bool $isUpdate = false): ActivityLog
    {
        $author = $user ?? auth()->user();
        $authorName = $author?->full_name ? explode(' ', trim($author->full_name))[0] : ($author?->username ?? 'Petugas');
        $action = $isUpdate ? 'diperbarui' : 'dicatat';
        $title = $isUpdate ? 'Pembaruan Catatan Observasi' : 'Pencatatan Observasi Baru';

        return ActivityLog::create([
            'type'        => 'observation',
            'title'       => $title,
            'description' => "Catatan observasi {$action} oleh {$authorName}: Pakan {$obs->feed_weight} kg, Bobot Maggot {$obs->maggot_weight} kg (Fase " . ucfirst($obs->phase_name) . ").",
            'user_id'     => $author?->id,
            'user_name'   => $authorName,
            'metadata'    => [
                'observation_id' => $obs->id,
                'feed_weight'    => (float) $obs->feed_weight,
                'maggot_weight'  => (float) $obs->maggot_weight,
                'phase_name'     => $obs->phase_name,
                'is_update'      => $isUpdate,
            ],
            'is_read'     => false,
        ]);
    }

    /**
     * Evaluasi anomali suhu dan kelembapan terhadap batas fase dan catat peringatan jika melewati batas.
     */
    public static function evaluateEnvironmentTelemetry(float $temperature, float $humidity, ?Cycle $cycle = null): void
    {
        $activeCycle = $cycle ?? Cycle::where('is_active', true)->first() ?? Cycle::latest('id')->first();
        $phaseName = strtolower($activeCycle->current_phase ?? 'penetasan');

        $setting = PhaseSetting::where('phase_name', $phaseName)->first();
        if (!$setting) {
            return;
        }

        $tempMin = (float) $setting->temp_bottom;
        $tempMax = (float) $setting->temp_top;
        $humidMin = (float) $setting->humid_bottom;
        $humidMax = (float) $setting->humid_top;

        $now = now();

        // 1. Cek Anomali Suhu
        if ($temperature < $tempMin) {
            $cacheKey = 'alert_temp_low_sent';
            if (!Cache::has($cacheKey)) {
                ActivityLog::create([
                    'type'        => 'temp_alert',
                    'title'       => 'Peringatan Suhu Terlalu Rendah',
                    'description' => "Suhu terdeteksi {$temperature}°C, berada di bawah batas minimum ideal ({$tempMin}°C - {$tempMax}°C).",
                    'metadata'    => ['temperature' => $temperature, 'min' => $tempMin, 'max' => $tempMax],
                    'is_read'     => false,
                ]);
                Cache::put($cacheKey, true, 60); // Jeda 60 detik sebelum peringatan berikutnya
            }
        } elseif ($temperature > $tempMax) {
            $cacheKey = 'alert_temp_high_sent';
            if (!Cache::has($cacheKey)) {
                ActivityLog::create([
                    'type'        => 'temp_alert',
                    'title'       => 'Peringatan Suhu Terlalu Tinggi',
                    'description' => "Suhu terdeteksi {$temperature}°C, melebihi batas maksimum ideal ({$tempMin}°C - {$tempMax}°C).",
                    'metadata'    => ['temperature' => $temperature, 'min' => $tempMin, 'max' => $tempMax],
                    'is_read'     => false,
                ]);
                Cache::put($cacheKey, true, 60);
            }
        }

        // 2. Cek Anomali Kelembapan
        if ($humidity < $humidMin) {
            $cacheKey = 'alert_humid_low_sent';
            if (!Cache::has($cacheKey)) {
                ActivityLog::create([
                    'type'        => 'humid_alert',
                    'title'       => 'Peringatan Kelembapan Terlalu Rendah',
                    'description' => "Kelembapan terdeteksi {$humidity}%, berada di bawah batas minimum ideal ({$humidMin}% - {$humidMax}%).",
                    'metadata'    => ['humidity' => $humidity, 'min' => $humidMin, 'max' => $humidMax],
                    'is_read'     => false,
                ]);
                Cache::put($cacheKey, true, 60);
            }
        } elseif ($humidity > $humidMax) {
            $cacheKey = 'alert_humid_high_sent';
            if (!Cache::has($cacheKey)) {
                ActivityLog::create([
                    'type'        => 'humid_alert',
                    'title'       => 'Peringatan Kelembapan Terlalu Tinggi',
                    'description' => "Kelembapan terdeteksi {$humidity}%, melebihi batas maksimum ideal ({$humidMin}% - {$humidMax}%).",
                    'metadata'    => ['humidity' => $humidity, 'min' => $humidMin, 'max' => $humidMax],
                    'is_read'     => false,
                ]);
                Cache::put($cacheKey, true, 60);
            }
        }
    }

    /**
     * Evaluasi transisi status perangkat IoT (Aktif / Tidak Aktif) dan catat perubahannya.
     */
    public static function evaluateDeviceStatus(bool $isCurrentlyOnline): void
    {
        $lastRecordedState = Cache::get('device_status_state'); // null, 'online', 'offline'

        if ($isCurrentlyOnline) {
            if ($lastRecordedState !== 'online') {
                Cache::put('device_status_state', 'online', 3600);
                if ($lastRecordedState !== null) {
                    ActivityLog::create([
                        'type'        => 'device_status',
                        'title'       => 'Status Perangkat: Aktif',
                        'description' => 'Perangkat monitoring IoT berhasil terhubung kembali dan aktif mengirimkan data telemetri.',
                        'metadata'    => ['status' => 'online', 'timestamp' => now()->toIso8601String()],
                        'is_read'     => false,
                    ]);
                }
            }
        } else {
            if ($lastRecordedState !== 'offline') {
                Cache::put('device_status_state', 'offline', 3600);
                if ($lastRecordedState !== null) {
                    ActivityLog::create([
                        'type'        => 'device_status',
                        'title'       => 'Status Perangkat: Tidak Aktif',
                        'description' => 'Perangkat monitoring IoT tidak merespons atau terputus selama lebih dari 20 detik.',
                        'metadata'    => ['status' => 'offline', 'timestamp' => now()->toIso8601String()],
                        'is_read'     => false,
                    ]);
                }
            }
        }
    }
}
