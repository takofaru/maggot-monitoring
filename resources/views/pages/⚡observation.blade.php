<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Cycle;
use App\Models\ObservationLog;

new class extends Component
{
    use WithPagination;

    public $selectedCycleId;
    public $selectedCycleName = '#'; // Menyimpan teks yang tampil di tombol
    public $latestCycle;
    public $observationData;
    public $isSelectedCurrent = True;

    public function mount()
    {
        $latest = Cycle::latest('id')->first();
        if ($latest) {
            $this->latestCycle = $latest->id;
            $this->selectedCycleName = $this->selectedCycleId = $latest->id;
            $this->isSelectedCurrent = True;
        }
    }

    public function selectCycle($id)
    {
        $this->selectedCycleName = $this->selectedCycleId = $id;
        $this->isSelectedCurrent = $this->selectedCycleId == $this->latestCycle;
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'cycleData' => Cycle::orderBy('created_at', 'asc')->get(),
            'observationData' => ObservationLog::with(['environmentLog', 'cycle'])
                ->where('cycle_id', $this->selectedCycleId)
                ->orderBy('timestamp', 'desc')
                ->paginate(10),
        ];
    }
};
?>

<div x-data="{ openForm: false, createObservation: false }" class="space-y-(--size-26) min-w-[922px]">
    <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold">
        Catatan Observasi
    </h1>
    <div class="inline-flex gap-(--size-10) justify-between w-full">
        <div class="flex flex-row items-center gap-(--size-10)">
            <div x-data="{ openDropdown: false }" class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px]">
                Siklus ke:
                <div class="relative inline-block">
                    <button
                        @click="openDropdown = !openDropdown"
                        type="button"
                        class="rounded-(--size-16) inline-flex justify-between items-center gap-(--size-10) input-text text-(--size-16) hover:bg-(--bg2-colour)"
                    >
                        <span>{{ $selectedCycleName }}</span>
                        <x-lucide-chevron-down class="w-(--size-16)"/>
                    </button>

                    <div
                        x-show="openDropdown"
                        @click.outside="openDropdown = false"
                        x-transition.opacity.duration.200ms
                        class="absolute left-0 top-full mt-(--size-10) w-(--size-492) bg-white border border-gray-300 rounded-(--size-16) shadow-lg z-50"
                        x-cloak
                    >
                        @foreach($cycleData as $item)
                            <button
                                type="button"
                                wire:click="selectCycle({{ $item->id }})"
                                @click="openDropdown = false"
                                class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 rounded"
                            >
                                <span class="font-semibold">Siklus {{ $item->id }}</span>
                                <span class="text-sm text-(--outline-colour)">
                                    {{ $item->start_date->translatedFormat('l, d F Y') }} - {{ $item->end_date ? $item->end_date->translatedFormat('l, d F Y') : "Sekarang"}}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
            <form wire:click="nextPhase" class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px]">
                <div class="gap-(--size-6)">
                    Fase terkini:
                    <span class="font-bold">{{ $cycleData->firstWhere('id', $selectedCycleId)->current_phase ?? '-' }}</span>
                </div>
                <button
                    type="button"
                    class="rounded-(--size-16) inline-flex justify-between items-center gap-(--size-10) px-(--size-16) py-(--size-6) input-button text-(--fg-colour)"
                >
                    <x-lucide-chevrons-right class="w-(--size-26)"/>
                </button>
            </form>
        </div>
        @if($isSelectedCurrent)
        <button
            @click="
                openForm = !openForm
                createObservation = !createObservation;
            "
            class="gap-(--size-10) input-button"
        >
            <x-lucide-plus class="w-(--size-26)"/>
            Tambah Catatan Baru
        </button>
        @endif
    </div>
    <div class="overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) min-w-max w-full">
        <table class="w-full text-left border-collapse">

            <thead class="border-b-[1.5px] border-(--prime-light-colour) bg-(--prime-colour)">
                <tr>
                    <th class="min-w-[238px]">Tanggal</th>
                    <th class="min-w-[109px]">Fase</th>
                    <th class="min-w-[84px]">Suhu</th>
                    <th class="min-w-[96px]">Kelembapan</th>
                    <th class="min-w-[82px]">Berat Pakan</th>
                    <th class="min-w-[79px]">Berat Maggot</th>
                    <th class="border-r-0 min-w-[114px]">Aksi</th>
                </tr>
            </thead>


            <tbody>
                @foreach($observationData as $item)
                <tr class="border-b-[1.5px] border-(--outline-colour) hover:bg-gray-50 transition-colors">
                    <td>{{ $item->timestamp->translatedFormat('l, d F Y') }}</td>
                    <td>{{ $item->phase_name}}</td>
                    <td>{{ $item->environmentLog->temperature ?? '-' }}&deg;C</td>
                    <td>{{ $item->environmentLog->humidity ?? '-' }}%</td>
                    <td>{{ $item->feed_weight}}kg</td>
                    <td>{{ $item->maggot_weight}}kg</td>
                    <td class="border-r-0 flex flex-row gap-(--size-10) w-full justify-center">
                        <button class="input-button p-(--size-10)">
                            <x-lucide-square-pen class="w-(--size-16)"/>
                        </button>
                        <button class="input-button p-(--size-10)">
                            <x-lucide-trash-2 class="w-(--size-16)"/>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="m-0">
        {{ $observationData->links() }}
    </div>
    <div
        x-show="openForm"
        x-cloak
        class="absolute inset-0 w-full h-screen backdrop-blur-sm"
    >
        <form
            :wire:submit="createObservation ? 'createObservationLog' : 'updateObservationLog'"
            id="loginForm"
            class="flex flex-col gap-(--size-26) m-(--size-42) px-(--size-26) py-(--size-42) bg-(--fg-colour) rounded-(--size-16)"
            @click.outside="
                openForm = false
                createObservation = false
            "
            value=""
        >
            <span x-show="createObservation" class="text-(length:--size-26) text-(--prime-colour) font-bold">Tambah Catatan Baru</span>
            <span x-show="!createObservation" x-cloak class="text-(length:--size-26) text-(--prime-colour) font-bold">Ubah Catatan</span>

            <div class="flex flex-row gap-(--size-16)">
                <div class="input-container w-full">
                    <label for="temp">Suhu yang Diamati</label>
                    <div class="flex flex-row items-center justify-between input-text">
                        <input
                            wire:model="temp"
                            id="temp"
                            type=""
                            placeholder="Masukkan Suhu yang Diamati"
                            class="w-full bg-transparent focus:outline-none"
                        />
                        &deg;C
                    </div>
                </div>
                <div class="input-container w-full">
                    <label for="humid">Kelembapan yang Diamati</label>
                    <div class="flex flex-row items-center justify-between input-text">
                        <input
                            wire:model="humid"
                            id="humid"
                            type=""
                            placeholder="Masukkan Kelembapan yang Diamati"
                            class="w-full bg-transparent focus:outline-none"
                        />
                        %
                    </div>
                </div>
            </div>
            <div class="flex flex-row gap-(--size-10) items-center">
                <div class="relative inline-block w-9 h-5">
                    <input wire:mode="useNewEnvironmentLog" id="switch-component" type="checkbox" class="peer appearance-none w-9 h-5 bg-(--bg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-6) checked:bg-(--prime-colour) checked:border-(--prime-colour) cursor-pointer transition-colors duration-300" />
                    <label for="switch-component" class="absolute top-0 left-0 w-5 h-5 bg-white rounded-(--size-6) border border-slate-300 shadow-sm transition-transform duration-300 peer-checked:translate-x-4 peer-checked:border-slate-800 cursor-pointer">
                    </label>
                </div>
                Gunakan Data Suhu dan Kelembapan yang Baru
            </div>
            <div class="flex flex-row gap-(--size-16)">
                <div class="input-container w-full">
                    <label for="maggot">Berat Pakan yang Diberikan</label>
                    <div class="flex flex-row items-center justify-between input-text">
                        <input
                            wire:model="feed"
                            id="feed"
                            type=""
                            placeholder="Masukkan Berat Pakan yang Diberikan"
                            class="w-full bg-transparent focus:outline-none"
                        />
                        kg
                    </div>
                </div>
                <div class="input-container w-full">
                    <label for="maggot">Berat Maggot yang Diamati</label>
                    <div class="flex flex-row items-center justify-between input-text">
                        <input
                            wire:model="maggot"
                            id="maggot"
                            type=""
                            placeholder="Masukkan Berat Maggot yang Diamati"
                            class="w-full bg-transparent focus:outline-none"
                        />
                        kg
                    </div>
                </div>
            </div>
            <div>
                <button
                    class="input-button"
                    @click="createObservation = False"
                >
                    <span x-show="createObservation" class="text-(length:--size-16) flex flex-row gap-(--size-10) items-center">
                        <x-lucide-plus class="w-(--size-26)"/>
                        Tambah Catatan
                    </span>
                    <span x-show="!createObservation" x-cloak class="text-(length:--size-26) flex flex-row gap-(--size-10) items-center">
                        <x-lucide-square-pen class="w-(--size-26)"/>
                        Ubah Catatan
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
