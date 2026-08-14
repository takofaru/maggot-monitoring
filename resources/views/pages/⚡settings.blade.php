<?php

use Livewire\Component;
use App\Models\PhaseSetting;
use App\Models\Cycle;
use App\Models\EnvironmentLog;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    // Fase 1: Penetasan
    public $penetasanTempMin = 27.00;
    public $penetasanTempMax = 30.00;
    public $penetasanHumidMin = 60.00;
    public $penetasanHumidMax = 80.00;

    // Fase 2: Pembesaran
    public $pembesaranTempMin = 26.00;
    public $pembesaranTempMax = 32.00;
    public $pembesaranHumidMin = 60.00;
    public $pembesaranHumidMax = 85.00;

    // Fase 3: Prepupa
    public $prepupaTempMin = 25.00;
    public $prepupaTempMax = 29.00;
    public $prepupaHumidMin = 50.00;
    public $prepupaHumidMax = 70.00;

    // Feedback message
    public string $flashMessage = '';

    public function mount()
    {
        $settings = PhaseSetting::all()->keyBy('phase_name');

        if (isset($settings['penetasan'])) {
            $this->penetasanTempMin = (float) $settings['penetasan']->temp_bottom;
            $this->penetasanTempMax = (float) $settings['penetasan']->temp_top;
            $this->penetasanHumidMin = (float) $settings['penetasan']->humid_bottom;
            $this->penetasanHumidMax = (float) $settings['penetasan']->humid_top;
        }

        if (isset($settings['pembesaran'])) {
            $this->pembesaranTempMin = (float) $settings['pembesaran']->temp_bottom;
            $this->pembesaranTempMax = (float) $settings['pembesaran']->temp_top;
            $this->pembesaranHumidMin = (float) $settings['pembesaran']->humid_bottom;
            $this->pembesaranHumidMax = (float) $settings['pembesaran']->humid_top;
        }

        if (isset($settings['prepupa'])) {
            $this->prepupaTempMin = (float) $settings['prepupa']->temp_bottom;
            $this->prepupaTempMax = (float) $settings['prepupa']->temp_top;
            $this->prepupaHumidMin = (float) $settings['prepupa']->humid_bottom;
            $this->prepupaHumidMax = (float) $settings['prepupa']->humid_top;
        }
    }

    public function changePhaseSettings()
    {
        $this->validate([
            'penetasanTempMin'  => 'required|numeric|between:0,100|lte:penetasanTempMax',
            'penetasanTempMax'  => 'required|numeric|between:0,100|gte:penetasanTempMin',
            'penetasanHumidMin' => 'required|numeric|between:0,100|lte:penetasanHumidMax',
            'penetasanHumidMax' => 'required|numeric|between:0,100|gte:penetasanHumidMin',

            'pembesaranTempMin'  => 'required|numeric|between:0,100|lte:pembesaranTempMax',
            'pembesaranTempMax'  => 'required|numeric|between:0,100|gte:pembesaranTempMin',
            'pembesaranHumidMin' => 'required|numeric|between:0,100|lte:pembesaranHumidMax',
            'pembesaranHumidMax' => 'required|numeric|between:0,100|gte:pembesaranHumidMin',

            'prepupaTempMin'  => 'required|numeric|between:0,100|lte:prepupaTempMax',
            'prepupaTempMax'  => 'required|numeric|between:0,100|gte:prepupaTempMin',
            'prepupaHumidMin' => 'required|numeric|between:0,100|lte:prepupaHumidMax',
            'prepupaHumidMax' => 'required|numeric|between:0,100|gte:prepupaHumidMin',
        ], [
            'penetasanTempMin.lte'  => 'Suhu minimum penetasan tidak boleh melebihi suhu maksimum.',
            'penetasanTempMax.gte'  => 'Suhu maksimum penetasan harus lebih besar atau sama dengan suhu minimum.',
            'penetasanHumidMin.lte' => 'Kelembapan minimum penetasan tidak boleh melebihi kelembapan maksimum.',
            'penetasanHumidMax.gte' => 'Kelembapan maksimum penetasan harus lebih besar atau sama dengan kelembapan minimum.',

            'pembesaranTempMin.lte'  => 'Suhu minimum pembesaran tidak boleh melebihi suhu maksimum.',
            'pembesaranTempMax.gte'  => 'Suhu maksimum pembesaran harus lebih besar atau sama dengan suhu minimum.',
            'pembesaranHumidMin.lte' => 'Kelembapan minimum pembesaran tidak boleh melebihi kelembapan maksimum.',
            'pembesaranHumidMax.gte' => 'Kelembapan maksimum pembesaran harus lebih besar atau sama dengan kelembapan minimum.',

            'prepupaTempMin.lte'  => 'Suhu minimum prepupa tidak boleh melebihi suhu maksimum.',
            'prepupaTempMax.gte'  => 'Suhu maksimum prepupa harus lebih besar atau sama dengan suhu minimum.',
            'prepupaHumidMin.lte' => 'Kelembapan minimum prepupa tidak boleh melebihi kelembapan maksimum.',
            'prepupaHumidMax.gte' => 'Kelembapan maksimum prepupa harus lebih besar atau sama dengan kelembapan minimum.',
        ]);

        // 1. Ambil data pengaturan lama dari database untuk pengecekan perubahan
        $existingSettings = PhaseSetting::all()->keyBy('phase_name');

        // Deteksi fase siklus yang saat ini sedang aktif
        $activeCycle = Cycle::where('is_active', true)->first();
        $currentPhase = $activeCycle ? strtolower($activeCycle->current_phase) : 'penetasan';

        if (!in_array($currentPhase, ['penetasan', 'pembesaran', 'prepupa'])) {
            $currentPhase = 'penetasan';
        }

        // Cek apakah ada perubahan pada nilai fase aktif yang sedang berjalan
        $activeOld = $existingSettings[$currentPhase] ?? null;
        $activeNewTMin = (float) $this->{$currentPhase . 'TempMin'};
        $activeNewTMax = (float) $this->{$currentPhase . 'TempMax'};
        $activeNewHMin = (float) $this->{$currentPhase . 'HumidMin'};
        $activeNewHMax = (float) $this->{$currentPhase . 'HumidMax'};

        $activePhaseChanged = (
            !$activeOld ||
            (float) $activeOld->temp_bottom !== $activeNewTMin ||
            (float) $activeOld->temp_top !== $activeNewTMax ||
            (float) $activeOld->humid_bottom !== $activeNewHMin ||
            (float) $activeOld->humid_top !== $activeNewHMax
        );

        // Cek apakah ada perubahan pada fase mana pun
        $anyPhaseChanged = false;
        foreach (['penetasan', 'pembesaran', 'prepupa'] as $p) {
            $old = $existingSettings[$p] ?? null;
            if (
                !$old ||
                (float) $old->temp_bottom !== (float) $this->{$p . 'TempMin'} ||
                (float) $old->temp_top !== (float) $this->{$p . 'TempMax'} ||
                (float) $old->humid_bottom !== (float) $this->{$p . 'HumidMin'} ||
                (float) $old->humid_top !== (float) $this->{$p . 'HumidMax'}
            ) {
                $anyPhaseChanged = true;
                break;
            }
        }

        // Jika tidak ada perubahan sama sekali, tidak perlu menyimpan dan tidak kirim ke MQTT
        if (!$anyPhaseChanged) {
            $this->flashMessage = 'Tidak ada perubahan pada pengaturan fase.';
            return;
        }

        // 2. Simpan perubahan ke database
        PhaseSetting::updateOrCreate(
            ['phase_name' => 'penetasan'],
            [
                'order'        => 1,
                'temp_bottom'  => $this->penetasanTempMin,
                'temp_top'     => $this->penetasanTempMax,
                'humid_bottom' => $this->penetasanHumidMin,
                'humid_top'    => $this->penetasanHumidMax,
            ]
        );

        PhaseSetting::updateOrCreate(
            ['phase_name' => 'pembesaran'],
            [
                'order'        => 2,
                'temp_bottom'  => $this->pembesaranTempMin,
                'temp_top'     => $this->pembesaranTempMax,
                'humid_bottom' => $this->pembesaranHumidMin,
                'humid_top'    => $this->pembesaranHumidMax,
            ]
        );

        PhaseSetting::updateOrCreate(
            ['phase_name' => 'prepupa'],
            [
                'order'        => 3,
                'temp_bottom'  => $this->prepupaTempMin,
                'temp_top'     => $this->prepupaTempMax,
                'humid_bottom' => $this->prepupaHumidMin,
                'humid_top'    => $this->prepupaHumidMax,
            ]
        );

        // 3. Hanya kirim ke MQTT jika batas fase aktif mengalami perubahan
        $phaseLabel = ucfirst($currentPhase);

        if ($activePhaseChanged) {
            $activePhaseData = [
                'phase_name' => $currentPhase,
                'temp_min'   => $activeNewTMin,
                'temp_max'   => $activeNewTMax,
                'humid_min'  => $activeNewHMin,
                'humid_max'  => $activeNewHMax,
            ];

            $mqttPublished = MqttService::publish('environmentLimit', $activePhaseData);

            if ($mqttPublished) {
                $this->flashMessage = "Pengaturan berhasil diperbarui dan dikirim ke MQTT topik 'environmentLimit' (Fase Aktif: {$phaseLabel}).";
            } else {
                $this->flashMessage = "Pengaturan berhasil disimpan di database (MQTT broker tidak terjangkau, Fase Aktif: {$phaseLabel}).";
            }
        } else {
            $this->flashMessage = "Pengaturan fase berhasil disimpan (Tidak ada perubahan pada batas fase aktif '{$phaseLabel}', data MQTT tidak dikirim).";
        }
    }

    public function with(): array
    {
        // 1. Ambil waktu server saat ini
        $currentTime = now();

        // 2. Ambil data terakhir dari cache atau database
        $cachedLastSeenStr = Cache::get('device_last_seen');
        $cachedLastSeen = $cachedLastSeenStr ? Carbon::parse($cachedLastSeenStr) : null;

        $latestEnv = EnvironmentLog::orderBy('timestamp', 'desc')->orderBy('id', 'desc')->first();
        $dbLastSeen = $latestEnv ? Carbon::parse($latestEnv->timestamp ?? $latestEnv->created_at) : null;

        $lastSeen = null;
        if ($cachedLastSeen && $dbLastSeen) {
            $lastSeen = $cachedLastSeen->greaterThan($dbLastSeen) ? $cachedLastSeen : $dbLastSeen;
        } else {
            $lastSeen = $cachedLastSeen ?? $dbLastSeen;
        }

        // 4. Hitung selisih waktu antara jam sekarang dengan data terakhir (dalam detik)
        $diffInSeconds = $lastSeen ? (int) abs($currentTime->diffInSeconds($lastSeen, false)) : null;

        // Status Perangkat: Online jika data masuk <= 40 detik yang lalu (interval normal 10 detik/data)
        $isOnline = ($diffInSeconds !== null && $diffInSeconds <= 40);

        return [
            'currentTime'   => $currentTime,
            'latestEnv'     => $latestEnv,
            'lastSeen'      => $lastSeen,
            'diffInSeconds' => $diffInSeconds,
            'isOnline'      => $isOnline,
        ];
    }
};
?>

<div class="space-y-(--size-26)">
    <!-- Header & Notifikasi Flash -->
    <div class="flex items-center justify-between">
        <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold">
            Pengaturan Perangkat
        </h1>
        @if ($flashMessage)
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 5000)"
                class="flex items-center gap-2 px-4 py-2 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl text-xs font-semibold shadow-sm transition-all"
            >
                <x-lucide-check-circle class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ $flashMessage }}</span>
            </div>
        @endif
    </div>

    <!-- Status Perangkat IoT, Jam Sekarang, Data Terakhir & Selisih Waktu (Check Setiap 10 Detik) -->
    <div wire:poll.10s class="flex flex-col lg:flex-row lg:items-center justify-between gap-(--size-16) px-(--size-26) py-(--size-16) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
        <!-- Bagian Kiri: Ikon & Status Online/Offline -->
        <div class="flex items-center gap-(--size-16)">
            <div class="p-3.5 bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0">
                <x-lucide-cpu class="w-(--size-26) h-(--size-26)" />
            </div>
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="text-sm font-bold text-gray-900">Status Perangkat IoT:</span>
                    @if($isOnline)
                        <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Online (Terhubung)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-300">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            Offline (Terputus)
                        </span>
                    @endif
                </div>
                <div class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                    @if($isOnline)
                        <span class="text-emerald-700 font-medium">Perangkat aktif mengirimkan data telemetri lingkungan.</span>
                        @if($latestEnv)
                            <span class="text-gray-400">|</span>
                            <span class="font-medium text-gray-700">Sensor: {{ $latestEnv->temperature }}&deg;C &bull; {{ $latestEnv->humidity }}%</span>
                        @endif
                    @else
                        <span class="text-gray-500">Tidak ada data sensor baru dari topik <code class="bg-gray-100 px-1 py-0.5 rounded font-mono text-[11px] text-gray-700">environmentData</code> (&gt; 40 detik).</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Bagian Kanan: Jam Sekarang, Data Terakhir Masuk & Selisih Detik -->
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-4 lg:gap-6 border-t lg:border-t-0 pt-3 lg:pt-0 text-xs">
            <!-- Jam Server Sekarang -->
            <div class="flex flex-col">
                <span class="text-gray-400 font-medium">Jam Sekarang:</span>
                <span class="font-bold text-gray-800 font-mono text-xs">
                    {{ $currentTime->translatedFormat('d M Y, H:i:s') }}
                </span>
            </div>

            <div class="hidden sm:block w-px h-8 bg-gray-200"></div>

            <!-- Data Terakhir Masuk -->
            <div class="flex flex-col">
                <span class="text-gray-400 font-medium">Data Terakhir:</span>
                <span class="font-bold text-(--prime-colour) font-mono text-xs">
                    {{ $lastSeen ? $lastSeen->translatedFormat('d M Y, H:i:s') : 'Belum ada data' }}
                </span>
            </div>

            <div class="hidden sm:block w-px h-8 bg-gray-200"></div>

            <!-- Selisih Waktu -->
            <div class="flex flex-col">
                <span class="text-gray-400 font-medium">Selisih Waktu:</span>
                <span class="font-bold {{ $isOnline ? 'text-emerald-700' : 'text-red-600' }}">
                    @if($diffInSeconds !== null)
                        {{ $diffInSeconds }} detik yang lalu
                    @else
                        -
                    @endif
                </span>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="changePhaseSettings" onsubmit="event.preventDefault();" id="changePhaseSettingsForm" class="space-y-(--size-26)">
        <!-- Grid 3 Fase: Penetasan, Pembesaran, Prepupa -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-(--size-26) w-full">
            
            <!-- 1. Fase Penetasan -->
            <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) w-full min-w-0 shadow-xs">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <x-lucide-egg class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Penetasan</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container w-full min-w-0">
                        <label>Batas Suhu</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('penetasanTempMin') border-red-500 @enderror">
                                <input
                                    wire:model="penetasanTempMin"
                                    id="penetasanTempMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Min"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0">s/d</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('penetasanTempMax') border-red-500 @enderror">
                                <input
                                    wire:model="penetasanTempMax"
                                    id="penetasanTempMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Max"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                        </div>
                        @error('penetasanTempMin')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                        @error('penetasanTempMax')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="input-container w-full min-w-0">
                        <label>Batas Kelembapan</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('penetasanHumidMin') border-red-500 @enderror">
                                <input
                                    wire:model="penetasanHumidMin"
                                    id="penetasanHumidMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Min"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0">s/d</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('penetasanHumidMax') border-red-500 @enderror">
                                <input
                                    wire:model="penetasanHumidMax"
                                    id="penetasanHumidMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Max"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                        </div>
                        @error('penetasanHumidMin')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                        @error('penetasanHumidMax')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- 2. Fase Pembesaran -->
            <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) w-full min-w-0 shadow-xs">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <x-lucide-worm class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Pembesaran</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container w-full min-w-0">
                        <label>Batas Suhu</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('pembesaranTempMin') border-red-500 @enderror">
                                <input
                                    wire:model="pembesaranTempMin"
                                    id="pembesaranTempMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Min"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0">s/d</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('pembesaranTempMax') border-red-500 @enderror">
                                <input
                                    wire:model="pembesaranTempMax"
                                    id="pembesaranTempMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Max"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                        </div>
                        @error('pembesaranTempMin')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                        @error('pembesaranTempMax')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="input-container w-full min-w-0">
                        <label>Batas Kelembapan</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('pembesaranHumidMin') border-red-500 @enderror">
                                <input
                                    wire:model="pembesaranHumidMin"
                                    id="pembesaranHumidMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Min"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0">s/d</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('pembesaranHumidMax') border-red-500 @enderror">
                                <input
                                    wire:model="pembesaranHumidMax"
                                    id="pembesaranHumidMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Max"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                        </div>
                        @error('pembesaranHumidMin')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                        @error('pembesaranHumidMax')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- 3. Fase Prepupa -->
            <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) w-full min-w-0 shadow-xs">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <x-lucide-bug class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Prepupa</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container w-full min-w-0">
                        <label>Batas Suhu</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('prepupaTempMin') border-red-500 @enderror">
                                <input
                                    wire:model="prepupaTempMin"
                                    id="prepupaTempMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Min"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0">s/d</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('prepupaTempMax') border-red-500 @enderror">
                                <input
                                    wire:model="prepupaTempMax"
                                    id="prepupaTempMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Max"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                        </div>
                        @error('prepupaTempMin')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                        @error('prepupaTempMax')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="input-container w-full min-w-0">
                        <label>Batas Kelembapan</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('prepupaHumidMin') border-red-500 @enderror">
                                <input
                                    wire:model="prepupaHumidMin"
                                    id="prepupaHumidMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Min"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0">s/d</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('prepupaHumidMax') border-red-500 @enderror">
                                <input
                                    wire:model="prepupaHumidMax"
                                    id="prepupaHumidMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Max"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                        </div>
                        @error('prepupaHumidMin')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                        @error('prepupaHumidMax')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

        </div>

        <!-- Tombol Simpan Pengaturan -->
        <button
            type="submit"
            wire:click.prevent="changePhaseSettings"
            wire:loading.attr="disabled"
            class="input-button w-full cursor-pointer hover:opacity-90 flex items-center justify-center gap-2"
        >
            <x-lucide-save class="w-(--size-26)"/>
            <span wire:loading.remove wire:target="changePhaseSettings">Simpan Pengaturan</span>
            <span wire:loading wire:target="changePhaseSettings">Menyimpan...</span>
        </button>
    </form>
</div>
