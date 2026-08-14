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
    // Fase Penetasan
    public $penetasanTempMin;
    public $penetasanTempMax;
    public $penetasanHumidMin;
    public $penetasanHumidMax;

    // Fase Pembesaran
    public $pembesaranTempMin;
    public $pembesaranTempMax;
    public $pembesaranHumidMin;
    public $pembesaranHumidMax;

    // Fase Prepupa
    public $prepupaTempMin;
    public $prepupaTempMax;
    public $prepupaHumidMin;
    public $prepupaHumidMax;

    public string $flashMessage = '';

    public function mount()
    {
        $settings = PhaseSetting::all()->keyBy('phase_name');

        // Penetasan
        $p = $settings->get('penetasan');
        $this->penetasanTempMin = $p?->temp_bottom ?? 27.00;
        $this->penetasanTempMax = $p?->temp_top ?? 30.00;
        $this->penetasanHumidMin = $p?->humid_bottom ?? 60.00;
        $this->penetasanHumidMax = $p?->humid_top ?? 80.00;

        // Pembesaran
        $b = $settings->get('pembesaran');
        $this->pembesaranTempMin = $b?->temp_bottom ?? 28.00;
        $this->pembesaranTempMax = $b?->temp_top ?? 32.00;
        $this->pembesaranHumidMin = $b?->humid_bottom ?? 50.00;
        $this->pembesaranHumidMax = $b?->humid_top ?? 70.00;

        // Prepupa
        $pr = $settings->get('prepupa');
        $this->prepupaTempMin = $pr?->temp_bottom ?? 26.00;
        $this->prepupaTempMax = $pr?->temp_top ?? 29.00;
        $this->prepupaHumidMin = $pr?->humid_bottom ?? 40.00;
        $this->prepupaHumidMax = $pr?->humid_top ?? 60.00;
    }

    public function changePhaseSettings()
    {
        $this->flashMessage = '';

        $this->validate([
            'penetasanTempMin'  => 'required|numeric|min:0|max:100|lte:penetasanTempMax',
            'penetasanTempMax'  => 'required|numeric|min:0|max:100|gte:penetasanTempMin',
            'penetasanHumidMin' => 'required|numeric|min:0|max:100|lte:penetasanHumidMax',
            'penetasanHumidMax' => 'required|numeric|min:0|max:100|gte:penetasanHumidMin',

            'pembesaranTempMin'  => 'required|numeric|min:0|max:100|lte:pembesaranTempMax',
            'pembesaranTempMax'  => 'required|numeric|min:0|max:100|gte:pembesaranTempMin',
            'pembesaranHumidMin' => 'required|numeric|min:0|max:100|lte:pembesaranHumidMax',
            'pembesaranHumidMax' => 'required|numeric|min:0|max:100|gte:pembesaranHumidMin',

            'prepupaTempMin'  => 'required|numeric|min:0|max:100|lte:prepupaTempMax',
            'prepupaTempMax'  => 'required|numeric|min:0|max:100|gte:prepupaTempMin',
            'prepupaHumidMin' => 'required|numeric|min:0|max:100|lte:prepupaHumidMax',
            'prepupaHumidMax' => 'required|numeric|min:0|max:100|gte:prepupaHumidMin',
        ], [
            '*.required' => 'Batas wajib diisi.',
            '*.numeric'  => 'Batas harus berupa angka.',
            '*.lte'      => 'Batas minimal tidak boleh lebih besar dari batas maksimal.',
            '*.gte'      => 'Batas maksimal tidak boleh lebih kecil dari batas minimal.',
        ]);

        // 1. Periksa batas fase aktif saat ini sebelum disimpan
        $activeCycle = Cycle::where('is_active', true)->first();
        $currentPhase = $activeCycle ? strtolower($activeCycle->current_phase) : 'penetasan';
        if (!in_array($currentPhase, ['penetasan', 'pembesaran', 'prepupa'])) {
            $currentPhase = 'penetasan';
        }

        $oldSetting = PhaseSetting::where('phase_name', $currentPhase)->first();

        $activeNewTMin = match($currentPhase) {
            'penetasan'  => (float) $this->penetasanTempMin,
            'pembesaran' => (float) $this->pembesaranTempMin,
            'prepupa'    => (float) $this->prepupaTempMin,
        };
        $activeNewTMax = match($currentPhase) {
            'penetasan'  => (float) $this->penetasanTempMax,
            'pembesaran' => (float) $this->pembesaranTempMax,
            'prepupa'    => (float) $this->prepupaTempMax,
        };
        $activeNewHMin = match($currentPhase) {
            'penetasan'  => (float) $this->penetasanHumidMin,
            'pembesaran' => (float) $this->pembesaranHumidMin,
            'prepupa'    => (float) $this->prepupaHumidMin,
        };
        $activeNewHMax = match($currentPhase) {
            'penetasan'  => (float) $this->penetasanHumidMax,
            'pembesaran' => (float) $this->pembesaranHumidMax,
            'prepupa'    => (float) $this->prepupaHumidMax,
        };

        $activePhaseChanged = true;
        if ($oldSetting) {
            $tMinDiff = abs((float)$oldSetting->temp_bottom - $activeNewTMin);
            $tMaxDiff = abs((float)$oldSetting->temp_top - $activeNewTMax);
            $hMinDiff = abs((float)$oldSetting->humid_bottom - $activeNewHMin);
            $hMaxDiff = abs((float)$oldSetting->humid_top - $activeNewHMax);

            if ($tMinDiff < 0.001 && $tMaxDiff < 0.001 && $hMinDiff < 0.001 && $hMaxDiff < 0.001) {
                $activePhaseChanged = false;
            }
        }

        // 2. Simpan batas fase ke Database
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

        // 3. Hitung selisih waktu antara jam sekarang dengan data terakhir (dalam detik)
        $diffInSeconds = $lastSeen ? (int) abs($currentTime->diffInSeconds($lastSeen, false)) : null;

        // Status Perangkat: Online jika data masuk <= 20 detik yang lalu (interval normal 10 detik/data)
        $isOnline = ($diffInSeconds !== null && $diffInSeconds <= 20);

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

<div class="space-y-(--size-26) w-full">
    <!-- Header & Notifikasi Flash -->
    <div class="flex items-center justify-between">
        <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold leading-tight">
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

    <!-- Status Alat & Waktu Terakhir Terhubung (Persis settings.png) -->
    <div wire:poll.10s class="flex flex-row items-center justify-between text-sm py-1 flex-wrap gap-2">
        <!-- Status Alat -->
        <div class="flex items-center gap-3 whitespace-nowrap shrink-0">
            <span class="font-bold text-(--text-colour) text-base">Status Alat:</span>
            @if($isOnline)
                <span class="px-3.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 inline-flex items-center gap-1.5 whitespace-nowrap shrink-0">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Online
                </span>
            @else
                <span class="px-3.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-300 inline-flex items-center gap-1.5 whitespace-nowrap shrink-0">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    Offline
                </span>
            @endif
        </div>

        <!-- Terakhir Terhubung -->
        <div class="text-xs text-gray-400 font-medium whitespace-nowrap shrink-0">
            @if($lastSeen)
                Terakhir terhubung pada {{ $lastSeen->translatedFormat('l, d F Y - H:i:s') }}
                @if($diffInSeconds !== null)
                    <span class="{{ $isOnline ? 'text-emerald-700' : 'text-red-500' }} font-bold">({{ $diffInSeconds }} detik yang lalu)</span>
                @endif
            @else
                Terakhir terhubung: Belum ada data
            @endif
        </div>
    </div>

    <!-- Form Batas Lingkungan Fase (Grid 2 Kolom Persis settings.png) -->
    <form wire:submit.prevent="changePhaseSettings" onsubmit="event.preventDefault();" id="changePhaseSettingsForm" class="space-y-(--size-26)">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-(--size-26) w-full">
            
            <!-- 1. Fase Penetasan -->
            <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) w-full min-w-0 shadow-xs">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                        <x-lucide-egg class="w-(--size-26) h-(--size-26)"/>
                    </div>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Penetasan</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container w-full min-w-0">
                        <label>Batas Suhu (&deg;C)</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('penetasanTempMin') border-red-500 @enderror">
                                <input
                                    wire:model="penetasanTempMin"
                                    id="penetasanTempMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0 px-1">sampai</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('penetasanTempMax') border-red-500 @enderror">
                                <input
                                    wire:model="penetasanTempMax"
                                    id="penetasanTempMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Maksimum"
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
                        <label>Batas Kelembapan (%)</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('penetasanHumidMin') border-red-500 @enderror">
                                <input
                                    wire:model="penetasanHumidMin"
                                    id="penetasanHumidMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0 px-1">sampai</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('penetasanHumidMax') border-red-500 @enderror">
                                <input
                                    wire:model="penetasanHumidMax"
                                    id="penetasanHumidMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Maksimum"
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
                    <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                        <x-lucide-worm class="w-(--size-26) h-(--size-26)"/>
                    </div>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Pembesaran</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container w-full min-w-0">
                        <label>Batas Suhu (&deg;C)</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('pembesaranTempMin') border-red-500 @enderror">
                                <input
                                    wire:model="pembesaranTempMin"
                                    id="pembesaranTempMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0 px-1">sampai</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('pembesaranTempMax') border-red-500 @enderror">
                                <input
                                    wire:model="pembesaranTempMax"
                                    id="pembesaranTempMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Maksimum"
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
                        <label>Batas Kelembapan (%)</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('pembesaranHumidMin') border-red-500 @enderror">
                                <input
                                    wire:model="pembesaranHumidMin"
                                    id="pembesaranHumidMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0 px-1">sampai</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('pembesaranHumidMax') border-red-500 @enderror">
                                <input
                                    wire:model="pembesaranHumidMax"
                                    id="pembesaranHumidMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Maksimum"
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
                    <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                        <x-lucide-bug class="w-(--size-26) h-(--size-26)"/>
                    </div>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Prepupa</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container w-full min-w-0">
                        <label>Batas Suhu (&deg;C)</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('prepupaTempMin') border-red-500 @enderror">
                                <input
                                    wire:model="prepupaTempMin"
                                    id="prepupaTempMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">&deg;C</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0 px-1">sampai</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('prepupaTempMax') border-red-500 @enderror">
                                <input
                                    wire:model="prepupaTempMax"
                                    id="prepupaTempMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Maksimum"
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
                        <label>Batas Kelembapan (%)</label>
                        <div class="flex flex-row gap-(--size-10) items-center w-full">
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('prepupaHumidMin') border-red-500 @enderror">
                                <input
                                    wire:model="prepupaHumidMin"
                                    id="prepupaHumidMin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none min-w-0"
                                />
                                <span class="text-gray-500 font-medium ml-1">%</span>
                            </div>
                            <span class="text-xs text-gray-500 font-medium shrink-0 px-1">sampai</span>
                            <div class="flex flex-row items-center justify-between input-text w-full min-w-0 @error('prepupaHumidMax') border-red-500 @enderror">
                                <input
                                    wire:model="prepupaHumidMax"
                                    id="prepupaHumidMax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="Nilai Maksimum"
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

        <!-- Tombol Simpan Pengaturan (Align Right Persis settings.png) -->
        <div class="flex justify-end pt-2">
            <button
                type="submit"
                wire:click.prevent="changePhaseSettings"
                wire:loading.attr="disabled"
                class="gap-(--size-10) input-button cursor-pointer hover:opacity-90 flex items-center px-8 shadow-xs"
            >
                <x-lucide-save class="w-(--size-26)"/>
                <span wire:loading.remove wire:target="changePhaseSettings">Simpan Pengaturan</span>
                <span wire:loading wire:target="changePhaseSettings">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
