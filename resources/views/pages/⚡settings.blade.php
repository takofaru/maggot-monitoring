<?php

use Livewire\Component;
use App\Models\PhaseSetting;
use App\Models\Cycle;
use App\Services\MqttService;

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

        // 1. Simpan perubahan ke database
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

        // 2. Tentukan fase siklus yang saat ini sedang aktif / berjalan
        $activeCycle = Cycle::where('is_active', true)->first();
        $currentPhase = $activeCycle ? strtolower($activeCycle->current_phase) : 'penetasan';

        if (!in_array($currentPhase, ['penetasan', 'pembesaran', 'prepupa'])) {
            $currentPhase = 'penetasan';
        }

        // Siapkan payload data fase yang sedang berlanjut
        $activePhaseData = match ($currentPhase) {
            'pembesaran' => [
                'phase_name'  => 'pembesaran',
                'temp_min'    => (float) $this->pembesaranTempMin,
                'temp_max'    => (float) $this->pembesaranTempMax,
                'humid_min'   => (float) $this->pembesaranHumidMin,
                'humid_max'   => (float) $this->pembesaranHumidMax,
                'TempBottom'  => (float) $this->pembesaranTempMin,
                'TempTop'     => (float) $this->pembesaranTempMax,
                'HumidBottom' => (float) $this->pembesaranHumidMin,
                'HumidTop'    => (float) $this->pembesaranHumidMax,
            ],
            'prepupa' => [
                'phase_name'  => 'prepupa',
                'temp_min'    => (float) $this->prepupaTempMin,
                'temp_max'    => (float) $this->prepupaTempMax,
                'humid_min'   => (float) $this->prepupaHumidMin,
                'humid_max'   => (float) $this->prepupaHumidMax,
                'TempBottom'  => (float) $this->prepupaTempMin,
                'TempTop'     => (float) $this->prepupaTempMax,
                'HumidBottom' => (float) $this->prepupaHumidMin,
                'HumidTop'    => (float) $this->prepupaHumidMax,
            ],
            default => [
                'phase_name'  => 'penetasan',
                'temp_min'    => (float) $this->penetasanTempMin,
                'temp_max'    => (float) $this->penetasanTempMax,
                'humid_min'   => (float) $this->penetasanHumidMin,
                'humid_max'   => (float) $this->penetasanHumidMax,
                'TempBottom'  => (float) $this->penetasanTempMin,
                'TempTop'     => (float) $this->penetasanTempMax,
                'HumidBottom' => (float) $this->penetasanHumidMin,
                'HumidTop'    => (float) $this->penetasanHumidMax,
            ],
        };

        // 3. Publikasikan ke topik MQTT 'environmentLimit'
        $mqttPublished = MqttService::publish('environmentLimit', $activePhaseData);

        $phaseLabel = ucfirst($currentPhase);
        if ($mqttPublished) {
            $this->flashMessage = "Pengaturan berhasil disimpan dan dikirim ke MQTT topik 'environmentLimit' (Fase Aktif: {$phaseLabel}).";
        } else {
            $this->flashMessage = "Pengaturan berhasil disimpan di database (MQTT broker tidak terjangkau, Fase Aktif: {$phaseLabel}).";
        }
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

    <form wire:submit="changePhaseSettings" id="changePhaseSettingsForm" class="space-y-(--size-26)">
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
            wire:loading.attr="disabled"
            class="input-button w-full cursor-pointer hover:opacity-90 flex items-center justify-center gap-2"
        >
            <x-lucide-save class="w-(--size-26)"/>
            <span wire:loading.remove wire:target="changePhaseSettings">Simpan Pengaturan</span>
            <span wire:loading wire:target="changePhaseSettings">Menyimpan & Mengirim ke MQTT...</span>
        </button>
    </form>
</div>
