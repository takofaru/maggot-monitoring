<?php

use Livewire\Component;
use App\Models\Cycle;
use App\Models\ObservationLog;
use App\Models\EnvironmentLog;
use App\Models\PhaseSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    public function with(): array
    {
        // 1. Inisiasi Query Siklus Aktif dari Database
        $activeCycle = Cycle::where('is_active', true)->first() ?? Cycle::latest('id')->first();
        $cycleId = $activeCycle?->id;
        $cycleNumber = $cycleId ?? 1;

        $dayNumber = 1;
        if ($activeCycle && $activeCycle->start_date) {
            $dayNumber = (int) Carbon::parse($activeCycle->start_date)->diffInDays(now()) + 1;
        }

        $currentPhase = $activeCycle ? ucfirst($activeCycle->current_phase) : 'Penetasan';

        // 2. Query Catatan Observasi untuk 3 Kartu KPI dari Database
        $obsLogs = ObservationLog::where('cycle_id', $cycleId)->orderBy('timestamp', 'asc')->orderBy('id', 'asc')->get();
        $totalFeed = (float) $obsLogs->sum('feed_weight');

        $latestObs = $obsLogs->last();
        $prevObs = $obsLogs->count() >= 2 ? $obsLogs->get($obsLogs->count() - 2) : null;

        $latestMaggotWeight = $latestObs ? (float) $latestObs->maggot_weight : 0.0;
        $feedDelta = $latestObs && $prevObs ? round((float)$latestObs->feed_weight - (float)$prevObs->feed_weight, 2) : 0.0;
        $maggotDelta = $latestObs && $prevObs ? round((float)$latestObs->maggot_weight - (float)$prevObs->maggot_weight, 2) : 0.0;

        $fcr = ($latestMaggotWeight > 0) ? round($totalFeed / $latestMaggotWeight, 2) : 0.0;
        $prevFeedTotal = (float) $obsLogs->take(max(0, $obsLogs->count() - 1))->sum('feed_weight');
        $prevFcr = ($prevObs && (float)$prevObs->maggot_weight > 0)
            ? round($prevFeedTotal / (float)$prevObs->maggot_weight, 2)
            : 0.0;
        $fcrDelta = ($fcr > 0 && $prevFcr > 0) ? round($fcr - $prevFcr, 2) : 0.0;

        $lastUpdateDate = $latestObs && $latestObs->timestamp
            ? Carbon::parse($latestObs->timestamp)->translatedFormat('l, d F Y')
            : now()->translatedFormat('l, d F Y');

        // 3. Inisiasi Data Telemetri dari Database (15 Data Terakhir Secara Kronologis)
        $envLogs = EnvironmentLog::latest('timestamp')->latest('id')->take(15)->get()->reverse()->values();

        $latestEnv = $envLogs->last();
        $tempVal = $latestEnv ? (float) $latestEnv->temperature : 0.0;
        $humidVal = $latestEnv ? (float) $latestEnv->humidity : 0.0;
        $envUpdateDate = $latestEnv
            ? Carbon::parse($latestEnv->timestamp ?? $latestEnv->created_at)->translatedFormat('l, d F Y - H:i:s')
            : now()->translatedFormat('l, d F Y - H:i:s');

        // Batas Ideal Fase Aktif
        $phaseSetting = PhaseSetting::where('phase_name', strtolower($activeCycle->current_phase ?? 'penetasan'))->first();
        $tempMin = $phaseSetting ? (float) $phaseSetting->temp_bottom : 27.0;
        $tempMax = $phaseSetting ? (float) $phaseSetting->temp_top : 30.0;
        $humidMin = $phaseSetting ? (float) $phaseSetting->humid_bottom : 60.0;
        $humidMax = $phaseSetting ? (float) $phaseSetting->humid_top : 80.0;

        $isTempNormal = ($tempVal >= $tempMin && $tempVal <= $tempMax);
        $isHumidNormal = ($humidVal >= $humidMin && $humidVal <= $humidMax);

        // Chart Labels dan Data Points
        $chartLabels = [];
        $chartTemp = [];
        $chartHumid = [];

        if ($envLogs->isNotEmpty()) {
            foreach ($envLogs as $log) {
                $chartLabels[] = Carbon::parse($log->timestamp ?? $log->created_at)->format('H:i:s');
                $chartTemp[] = (float) $log->temperature;
                $chartHumid[] = (float) $log->humidity;
            }
        } else {
            $chartLabels = ['00:00:00'];
            $chartTemp = [0];
            $chartHumid = [0];
        }

        // 4. Status Perangkat IoT (Liveness Check)
        $cachedLastSeenStr = Cache::get('device_last_seen');
        $cachedLastSeen = $cachedLastSeenStr ? Carbon::parse($cachedLastSeenStr) : null;
        $dbLastSeen = $latestEnv ? Carbon::parse($latestEnv->timestamp ?? $latestEnv->created_at) : null;

        $lastSeen = ($cachedLastSeen && $dbLastSeen)
            ? ($cachedLastSeen->greaterThan($dbLastSeen) ? $cachedLastSeen : $dbLastSeen)
            : ($cachedLastSeen ?? $dbLastSeen);

        $diffInSeconds = $lastSeen ? (int) abs(now()->diffInSeconds($lastSeen, false)) : null;
        $isDeviceOnline = ($diffInSeconds !== null && $diffInSeconds <= 20);

        // 5. Log Aktivitas & Peringatan Terkini
        $activities = [];

        if ($latestEnv) {
            if ($tempVal < $tempMin) {
                $activities[] = [
                    'type' => 'temp_low',
                    'title' => 'Suhu terlalu rendah',
                    'desc' => "Suhu mencapai {$tempVal}°C (Batas ideal: {$tempMin}°C - {$tempMax}°C)",
                    'time' => $lastSeen ? $lastSeen->diffForHumans() : 'Baru saja',
                ];
            } elseif ($tempVal > $tempMax) {
                $activities[] = [
                    'type' => 'temp_high',
                    'title' => 'Suhu terlalu tinggi',
                    'desc' => "Suhu mencapai {$tempVal}°C (Batas ideal: {$tempMin}°C - {$tempMax}°C)",
                    'time' => $lastSeen ? $lastSeen->diffForHumans() : 'Baru saja',
                ];
            }

            if ($humidVal < $humidMin) {
                $activities[] = [
                    'type' => 'humid_low',
                    'title' => 'Kelembapan terlalu rendah',
                    'desc' => "Kelembapan mencapai {$humidVal}% (Batas ideal: {$humidMin}% - {$humidMax}%)",
                    'time' => $lastSeen ? $lastSeen->diffForHumans() : 'Baru saja',
                ];
            } elseif ($humidVal > $humidMax) {
                $activities[] = [
                    'type' => 'humid_high',
                    'title' => 'Kelembapan terlalu tinggi',
                    'desc' => "Kelembapan mencapai {$humidVal}% (Batas ideal: {$humidMin}% - {$humidMax}%)",
                    'time' => $lastSeen ? $lastSeen->diffForHumans() : 'Baru saja',
                ];
            }
        }

        if ($latestObs) {
            $activities[] = [
                'type' => 'feed',
                'title' => 'Pencatatan Observasi Harian',
                'desc' => "Pemberian pakan {$latestObs->feed_weight} kg | Bobot maggot {$latestObs->maggot_weight} kg",
                'time' => $latestObs->timestamp ? Carbon::parse($latestObs->timestamp)->diffForHumans() : 'Hari ini',
            ];
        }

        $this->dispatch('telemetry-updated', [
            'labels'   => $chartLabels,
            'temp'     => $chartTemp,
            'humid'    => $chartHumid,
            'tempMin'  => (float) $tempMin,
            'tempMax'  => (float) $tempMax,
            'humidMin' => (float) $humidMin,
            'humidMax' => (float) $humidMax,
        ]);

        return [
            'cycleNumber'        => $cycleNumber,
            'dayNumber'          => $dayNumber,
            'currentPhase'       => $currentPhase,
            'totalFeed'          => $totalFeed,
            'feedDelta'          => $feedDelta,
            'latestMaggotWeight' => $latestMaggotWeight,
            'maggotDelta'        => $maggotDelta,
            'fcr'                => $fcr,
            'fcrDelta'           => $fcrDelta,
            'lastUpdateDate'     => $lastUpdateDate,
            'tempVal'            => $tempVal,
            'humidVal'           => $humidVal,
            'tempMin'            => $tempMin,
            'tempMax'            => $tempMax,
            'humidMin'           => $humidMin,
            'humidMax'           => $humidMax,
            'isTempNormal'       => $isTempNormal,
            'isHumidNormal'      => $isHumidNormal,
            'envUpdateDate'      => $envUpdateDate,
            'chartLabels'        => $chartLabels,
            'chartTemp'          => $chartTemp,
            'chartHumid'         => $chartHumid,
            'isDeviceOnline'     => $isDeviceOnline,
            'activities'         => $activities,
        ];
    }
};
?>

<div wire:poll.5s class="space-y-(--size-26) w-full">
    <!-- Header Dashboard & Status Indikator di Samping Judul & Tombol Lonceng Aktivitas -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3.5">
            <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold leading-tight">
                Dashboard
            </h1>

            <!-- Dot Status Perangkat IoT di Samping Judul -->
            <div class="w-7 h-7 rounded-full {{ $isDeviceOnline ? 'bg-green-200' : 'bg-red-200' }} flex items-center justify-center shrink-0" title="{{ $isDeviceOnline ? 'Perangkat Online' : 'Perangkat Offline' }}">
                <span class="w-2.5 h-2.5 rounded-full {{ $isDeviceOnline ? 'bg-(--prime-colour)' : 'bg-red-500' }}"></span>
            </div>
        </div>

        <!-- Tombol Lonceng Notifikasi Global & Pop-up Toast -->
        <livewire:notification-bell />
    </div>

    <!-- 3 Pill Badges Status Siklus -->
    <div class="flex flex-nowrap items-center gap-(--size-10)">
        <!-- Pill 1: Siklus ke -->
        <div class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs text-sm font-semibold text-(--text-colour) whitespace-nowrap shrink-0">
            <x-lucide-refresh-cw class="w-(--size-16) text-(--prime-colour)"/>
            <span>Siklus ke: {{ sprintf('%02d', $cycleNumber) }}</span>
        </div>

        <!-- Pill 2: Hari ke -->
        <div class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs text-sm font-semibold text-(--text-colour) whitespace-nowrap shrink-0">
            <x-lucide-calendar class="w-(--size-16) text-(--prime-colour)"/>
            <span>Hari ke: {{ sprintf('%02d', $dayNumber) }}</span>
        </div>

        <!-- Pill 3: Fase Sekarang -->
        <div class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs text-sm font-semibold text-(--text-colour) whitespace-nowrap shrink-0">
            <x-lucide-move-up-right class="w-(--size-16) text-(--prime-colour)"/>
            <span>Fase Sekarang: {{ $currentPhase }}</span>
        </div>
    </div>

    <!-- 3 Kartu Ringkasan KPI Utama (Baris Atas) -->
    <div class="grid grid-cols-3 gap-(--size-26) w-full">
        <!-- 1. Total Pakan Kumulatif -->
        <div class="flex flex-col justify-between gap-(--size-16) p-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex flex-row items-center gap-(--size-16)">
                <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                    <x-lucide-apple class="w-(--size-26) h-(--size-26)"/>
                </div>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                    Total Pakan Kumulatif
                </span>
            </div>
            <div>
                <div class="flex items-baseline gap-2 flex-nowrap">
                    <span class="text-(length:--size-42) font-extrabold text-(--prime-colour) leading-none">
                        {{ number_format($totalFeed, 1) }}
                    </span>
                    <span class="text-base font-bold text-gray-500">kg</span>
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-semibold rounded-full whitespace-nowrap">
                        {{ $feedDelta >= 0 ? '+' : '' }}{{ number_format($feedDelta, 1) }}kg dari sebelumnya
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">
                    Diperbaharui pada {{ $lastUpdateDate }}
                </p>
            </div>
        </div>

        <!-- 2. Berat Maggot -->
        <div class="flex flex-col justify-between gap-(--size-16) p-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex flex-row items-center gap-(--size-16)">
                <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                    <x-lucide-weight class="w-(--size-26) h-(--size-26)"/>
                </div>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                    Berat Maggot
                </span>
            </div>
            <div>
                <div class="flex items-baseline gap-2 flex-nowrap">
                    <span class="text-(length:--size-42) font-extrabold text-(--prime-colour) leading-none">
                        {{ number_format($latestMaggotWeight, 1) }}
                    </span>
                    <span class="text-base font-bold text-gray-500">kg</span>
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-semibold rounded-full whitespace-nowrap">
                        {{ $maggotDelta >= 0 ? '+' : '' }}{{ number_format($maggotDelta, 1) }}kg dari sebelumnya
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">
                    Diperbaharui pada {{ $lastUpdateDate }}
                </p>
            </div>
        </div>

        <!-- 3. Konversi Rasio Pakan Sementara (FCR) -->
        <div class="flex flex-col justify-between gap-(--size-16) p-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex flex-row items-center gap-(--size-16)">
                <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                    <x-lucide-ruler class="w-(--size-26) h-(--size-26)"/>
                </div>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                    Konversi Rasio Pakan Sementara
                </span>
            </div>
            <div>
                <div class="flex items-baseline gap-1.5 flex-nowrap">
                    <span class="text-(length:--size-42) font-extrabold text-(--prime-colour) leading-none">
                        {{ number_format($fcr, 1) }}
                    </span>
                    <span class="text-xs font-semibold text-gray-500">per kg maggot</span>
                </div>
                <div class="mt-2">
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-semibold rounded-full whitespace-nowrap">
                        {{ $fcrDelta >= 0 ? '+' : '' }}{{ number_format($fcrDelta, 1) }} dari sebelumnya
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">
                    Diperbaharui pada {{ $lastUpdateDate }}
                </p>
            </div>
        </div>
    </div>

    <!-- Baris Bawah: Suhu & Kelembapan Bersampingan (Grid 2 Kolom) -->
    <div class="grid grid-cols-2 gap-(--size-26) w-full items-stretch">

        <!-- 1. Box Grafik Suhu (Col 1) -->
        <div class="col-span-1 flex flex-col justify-between gap-(--size-16) p-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div>
                <div class="flex flex-row items-center justify-between">
                    <div class="flex flex-row items-center gap-(--size-16)">
                        <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                            <x-lucide-thermometer class="w-(--size-26) h-(--size-26)"/>
                        </div>
                        <span class="text-(--prime-colour) text-(length:--size-26) font-bold">
                            Suhu
                        </span>
                    </div>

                    <!-- Keterangan Garis Batas Suhu -->
                    <div class="flex items-center gap-3 text-xs whitespace-nowrap shrink-0">
                        <span class="flex items-center gap-1 text-gray-600 font-medium">
                            <span class="w-3 h-0.5 bg-[#163428] rounded"></span> Aktual
                        </span>
                        <span class="flex items-center gap-1 text-red-600 font-medium">
                            <span class="w-3 h-0.5 bg-red-500 border-b border-dashed border-red-500"></span> Maks ({{ $tempMax }}&deg;C)
                        </span>
                        <span class="flex items-center gap-1 text-blue-600 font-medium">
                            <span class="w-3 h-0.5 bg-blue-500 border-b border-dashed border-blue-500"></span> Min ({{ $tempMin }}&deg;C)
                        </span>
                    </div>
                </div>

                <div class="mt-2">
                    <div class="flex items-center gap-3">
                        <span class="text-(length:--size-42) font-extrabold text-gray-900 leading-none">
                            {{ number_format($tempVal, 1) }}&deg;C
                        </span>
                        @if($isTempNormal)
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">Normal</span>
                        @else
                            <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Peringatan</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-400 mt-1 mb-1">
                        Diperbaharui pada {{ $envUpdateDate }}
                    </p>
                </div>
            </div>

            <!-- Canvas Chart Suhu dengan Range 0 sampai Suhu Tertinggi (Tinggi Tetap 320px, Lebar Fleksibel & Live Updating) -->
            <div wire:ignore class="relative w-full h-[320px] border border-gray-100 rounded-xl p-2 bg-gray-50/50">
                <canvas id="tempChartCanvas" style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>

        <!-- 2. Box Grafik Kelembapan (Col 2) -->
        <div class="col-span-1 flex flex-col justify-between gap-(--size-16) p-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div>
                <div class="flex flex-row items-center justify-between">
                    <div class="flex flex-row items-center gap-(--size-16)">
                        <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                            <x-lucide-droplets class="w-(--size-26) h-(--size-26)"/>
                        </div>
                        <span class="text-(--prime-colour) text-(length:--size-26) font-bold">
                            Kelembapan
                        </span>
                    </div>

                    <!-- Keterangan Garis Batas Kelembapan -->
                    <div class="flex items-center gap-3 text-xs whitespace-nowrap shrink-0">
                        <span class="flex items-center gap-1 text-gray-600 font-medium">
                            <span class="w-3 h-0.5 bg-[#163428] rounded"></span> Aktual
                        </span>
                        <span class="flex items-center gap-1 text-red-600 font-medium">
                            <span class="w-3 h-0.5 bg-red-500 border-b border-dashed border-red-500"></span> Maks ({{ $humidMax }}%)
                        </span>
                        <span class="flex items-center gap-1 text-blue-600 font-medium">
                            <span class="w-3 h-0.5 bg-blue-500 border-b border-dashed border-blue-500"></span> Min ({{ $humidMin }}%)
                        </span>
                    </div>
                </div>

                <div class="mt-2">
                    <div class="flex items-center gap-3">
                        <span class="text-(length:--size-42) font-extrabold text-gray-900 leading-none">
                            {{ number_format($humidVal, 1) }}%
                        </span>
                        @if($isHumidNormal)
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">Normal</span>
                        @else
                            <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Peringatan</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-400 mt-1 mb-1">
                        Diperbaharui pada {{ $envUpdateDate }}
                    </p>
                </div>
            </div>

            <!-- Canvas Chart Kelembapan dengan Range 0 - 100% (Tinggi Tetap 320px, Lebar Fleksibel & Live Updating) -->
            <div wire:ignore class="relative w-full h-[320px] border border-gray-100 rounded-xl p-2 bg-gray-50/50">
                <canvas id="humidChartCanvas" style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>

    </div>
</div>

@script
<script>
    let tempChart = null;
    let humidChart = null;

    function initOrUpdateTempChart(labels, data, tMin, tMax) {
        const canvas = document.getElementById('tempChartCanvas');
        if (!canvas || typeof Chart === 'undefined') return;

        let minLine = Array(labels.length).fill(tMin);
        let maxLine = Array(labels.length).fill(tMax);
        let maxObserved = Math.max(...data, tMax, 0);
        let yMax = Math.max(35, Math.ceil(maxObserved / 5) * 5);

        if (tempChart) {
            tempChart.data.labels = labels;
            tempChart.data.datasets[0].data = data;
            tempChart.data.datasets[1].data = maxLine;
            tempChart.data.datasets[1].label = 'Batas Maksimum (' + tMax + '°C)';
            tempChart.data.datasets[2].data = minLine;
            tempChart.data.datasets[2].label = 'Batas Minimum (' + tMin + '°C)';
            tempChart.options.scales.y.max = yMax;
            tempChart.options.scales.y.ticks.stepSize = yMax <= 40 ? 5 : 10;
            tempChart.update('none');
            return;
        }

        const existing = Chart.getChart(canvas);
        if (existing) {
            existing.destroy();
        }

        tempChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Suhu Aktual (°C)',
                        data: data,
                        borderColor: '#163428',
                        backgroundColor: 'rgba(22, 52, 40, 0.08)',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#163428',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Batas Maksimum (' + tMax + '°C)',
                        data: maxLine,
                        borderColor: '#EF4444',
                        borderWidth: 1.5,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    },
                    {
                        label: 'Batas Minimum (' + tMin + '°C)',
                        data: minLine,
                        borderColor: '#3B82F6',
                        borderWidth: 1.5,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#163428',
                        callbacks: {
                            label: (ctx) => ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1) + '°C'
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: yMax,
                        ticks: {
                            stepSize: yMax <= 40 ? 5 : 10,
                            callback: (v) => v + '°C'
                        },
                        grid: { color: '#E5E7EB' }
                    },
                    x: {
                        grid: { color: '#F3F4F6' }
                    }
                }
            }
        });
    }

    function initOrUpdateHumidChart(labels, data, hMin, hMax) {
        const canvas = document.getElementById('humidChartCanvas');
        if (!canvas || typeof Chart === 'undefined') return;

        let minLine = Array(labels.length).fill(hMin);
        let maxLine = Array(labels.length).fill(hMax);

        if (humidChart) {
            humidChart.data.labels = labels;
            humidChart.data.datasets[0].data = data;
            humidChart.data.datasets[1].data = maxLine;
            humidChart.data.datasets[1].label = 'Batas Maksimum (' + hMax + '%)';
            humidChart.data.datasets[2].data = minLine;
            humidChart.data.datasets[2].label = 'Batas Minimum (' + hMin + '%)';
            humidChart.update('none');
            return;
        }

        const existing = Chart.getChart(canvas);
        if (existing) {
            existing.destroy();
        }

        humidChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Kelembapan Aktual (%)',
                        data: data,
                        borderColor: '#163428',
                        backgroundColor: 'rgba(22, 52, 40, 0.08)',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#163428',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Batas Maksimum (' + hMax + '%)',
                        data: maxLine,
                        borderColor: '#EF4444',
                        borderWidth: 1.5,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    },
                    {
                        label: 'Batas Minimum (' + hMin + '%)',
                        data: minLine,
                        borderColor: '#3B82F6',
                        borderWidth: 1.5,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#163428',
                        callbacks: {
                            label: (ctx) => ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1) + '%'
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            stepSize: 10,
                            callback: (v) => v + '%'
                        },
                        grid: { color: '#E5E7EB' }
                    },
                    x: {
                        grid: { color: '#F3F4F6' }
                    }
                }
            }
        });
    }

    const checkAndInit = () => {
        initOrUpdateTempChart(@js($chartLabels), @js($chartTemp), {{ (float)$tempMin }}, {{ (float)$tempMax }});
        initOrUpdateHumidChart(@js($chartLabels), @js($chartHumid), {{ (float)$humidMin }}, {{ (float)$humidMax }});
    };

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(checkAndInit, 50);
    } else {
        document.addEventListener('DOMContentLoaded', checkAndInit);
    }

    $wire.on('telemetry-updated', (event) => {
        const payload = Array.isArray(event) ? event[0] : event;
        if (payload) {
            initOrUpdateTempChart(payload.labels, payload.temp, payload.tempMin, payload.tempMax);
            initOrUpdateHumidChart(payload.labels, payload.humid, payload.humidMin, payload.humidMax);
        }
    });
</script>
@endscript
