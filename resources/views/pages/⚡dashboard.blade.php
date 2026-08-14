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
            <div class="w-7 h-7 rounded-full bg-[#E3EFE9] flex items-center justify-center shrink-0" title="{{ $isDeviceOnline ? 'Perangkat Online' : 'Perangkat Offline' }}">
                <span class="w-2.5 h-2.5 rounded-full {{ $isDeviceOnline ? 'bg-[#163428]' : 'bg-red-500' }}"></span>
            </div>
        </div>

        <!-- Tombol Lonceng Notifikasi & Aktivitas (Dropdown Popover) -->
        <div class="relative" x-data="{ open: false }">
            <button
                type="button"
                @click="open = !open"
                class="relative p-2.5 bg-(--fg-colour) border-[1.5px] border-(--outline-colour) rounded-(--size-16) hover:bg-gray-50 focus:outline-none shadow-xs transition-colors cursor-pointer flex items-center justify-center text-(--prime-colour)"
                title="Lihat Aktivitas & Notifikasi"
            >
                <x-lucide-bell class="w-5 h-5"/>
                
                @if(count($activities) > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 w-4">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 text-[9px] font-bold text-white items-center justify-center leading-none">
                            {{ count($activities) }}
                        </span>
                    </span>
                @endif
            </button>

            <!-- Dropdown Popover List Aktivitas -->
            <div
                x-show="open"
                @click.outside="open = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95 transform translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 transform translate-y-2"
                x-cloak
                class="absolute right-0 mt-2 w-80 sm:w-96 bg-(--fg-colour) border-[1.5px] border-(--outline-colour) rounded-(--size-16) shadow-xl z-50 overflow-hidden"
            >
                <!-- Header Dropdown -->
                <div class="p-3.5 border-b border-gray-100 bg-gray-50/75 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-lucide-bell class="w-4 h-4 text-(--prime-colour)"/>
                        <span class="font-bold text-sm text-(--prime-colour)">Aktivitas & Peringatan</span>
                    </div>
                    <span class="text-xs bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full font-semibold">
                        {{ count($activities) }} Catatan
                    </span>
                </div>

                <!-- Isi Daftar Aktivitas -->
                <div class="max-h-80 overflow-y-auto p-3 flex flex-col gap-2.5 divide-y divide-gray-50">
                    @forelse($activities as $act)
                        <div class="pt-2 first:pt-0 flex items-start gap-2.5">
                            <div class="p-2 rounded-(--size-10) shrink-0 {{ str_contains($act['type'], 'temp') || str_contains($act['type'], 'humid') ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                @if($act['type'] === 'temp_low')
                                    <x-lucide-thermometer-snowflake class="w-4 h-4"/>
                                @elseif($act['type'] === 'temp_high')
                                    <x-lucide-thermometer-sun class="w-4 h-4"/>
                                @elseif(str_contains($act['type'], 'humid'))
                                    <x-lucide-droplets class="w-4 h-4"/>
                                @else
                                    <x-lucide-clipboard-check class="w-4 h-4"/>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="font-bold text-xs text-gray-900 leading-tight">
                                    {{ $act['title'] }}
                                </h4>
                                <p class="text-[11px] text-gray-600 mt-0.5 leading-snug">
                                    {{ $act['desc'] }}
                                </p>
                                <span class="text-[10px] text-gray-400 font-medium mt-0.5 block">
                                    {{ $act['time'] }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-400 text-xs">
                            <x-lucide-bell-off class="w-6 h-6 mx-auto mb-1.5 opacity-40"/>
                            Belum ada aktivitas atau peringatan tercatat.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
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

            <!-- Canvas Chart Suhu dengan Range 0 sampai Suhu Tertinggi (Tinggi Tetap 320px, Lebar Fleksibel) -->
            <div class="relative w-full h-[320px] border border-gray-100 rounded-xl p-2 bg-gray-50/50" wire:ignore>
                <canvas x-data="{
                    chart: null,
                    init() {
                        this.$nextTick(() => this.renderChart());
                    },
                    renderChart() {
                        let labels = @js($chartLabels);
                        let data = @js($chartTemp);
                        let tMin = {{ (float) $tempMin }};
                        let tMax = {{ (float) $tempMax }};

                        let minLine = Array(labels.length).fill(tMin);
                        let maxLine = Array(labels.length).fill(tMax);

                        // Hitung suhu tertinggi untuk rentang sumbu Y dinamis (0 s/d tertinggi)
                        let maxObserved = Math.max(...data, tMax, 0);
                        let yMax = Math.max(35, Math.ceil(maxObserved / 5) * 5);

                        if (this.chart) {
                            this.chart.data.labels = labels;
                            this.chart.data.datasets[0].data = data;
                            this.chart.data.datasets[1].data = maxLine;
                            this.chart.data.datasets[1].label = 'Batas Maksimum (' + tMax + '°C)';
                            this.chart.data.datasets[2].data = minLine;
                            this.chart.data.datasets[2].label = 'Batas Minimum (' + tMin + '°C)';
                            this.chart.options.scales.y.max = yMax;
                            this.chart.update('none');
                            return;
                        }

                        if (typeof Chart === 'undefined') {
                            setTimeout(() => this.renderChart(), 100);
                            return;
                        }

                        this.chart = new Chart(this.$el, {
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
                }"
                x-on:livewire:updated.window="renderChart()"
                style="width: 100%; height: 100%;"></canvas>
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

            <!-- Canvas Chart Kelembapan dengan Range 0 - 100% (Tinggi Tetap 320px, Lebar Fleksibel) -->
            <div class="relative w-full h-[320px] border border-gray-100 rounded-xl p-2 bg-gray-50/50" wire:ignore>
                <canvas x-data="{
                    chart: null,
                    init() {
                        this.$nextTick(() => this.renderChart());
                    },
                    renderChart() {
                        let labels = @js($chartLabels);
                        let data = @js($chartHumid);
                        let hMin = {{ (float) $humidMin }};
                        let hMax = {{ (float) $humidMax }};

                        let minLine = Array(labels.length).fill(hMin);
                        let maxLine = Array(labels.length).fill(hMax);

                        if (this.chart) {
                            this.chart.data.labels = labels;
                            this.chart.data.datasets[0].data = data;
                            this.chart.data.datasets[1].data = maxLine;
                            this.chart.data.datasets[1].label = 'Batas Maksimum (' + hMax + '%)';
                            this.chart.data.datasets[2].data = minLine;
                            this.chart.data.datasets[2].label = 'Batas Minimum (' + hMin + '%)';
                            this.chart.update('none');
                            return;
                        }

                        if (typeof Chart === 'undefined') {
                            setTimeout(() => this.renderChart(), 100);
                            return;
                        }

                        this.chart = new Chart(this.$el, {
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
                }"
                x-on:livewire:updated.window="renderChart()"
                style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>

    </div>
</div>
