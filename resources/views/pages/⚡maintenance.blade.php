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
        // Ambil siklus terakhir sebagai default saat halaman dimuat
        $latest = Cycle::latest('id')->first();
        if ($latest) {
            $this->latestCycle = $latest->id;
            $this->selectedCycleName = $this->selectedCycleId = $latest->id;
            $this->isSelectedCurrent = True;
        }
    }

    // Fungsi ini dipanggil saat item di custom dropdown di-klik
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
            'observationData' => ObservationLog::with('environmentLog')
                ->where('cycle_id', $this->selectedCycleId)
                ->orderBy('timestamp', 'desc')
                ->paginate(14),
        ];
    }
};
?>

<div class="space-y-(--size-26)">
    <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold">
        Catatan Pemeliharaan
    </h1>
    <div class="inline-flex gap-(--size-10)">
        <div x-data="{ open: false }" class="inline-flex gap-(--size-10) items-center">
            <x-lucide-refresh-cw class="w-(--size-16)"/>
            Siklus ke:
            <div x-data="{ open: false }" class="relative inline-block">
                <button
                    @click="open = !open"
                    type="button"
                    class="w-full inline-flex justify-between items-center gap-(--size-10) input-text text-(--size-16) bg-(--fg-colour) hover:bg-(--bg-colour)"
                >
                    <span>{{ $selectedCycleName }}</span>
                    <x-lucide-chevron-down class="w-(--size-16)"/>
                </button>

                <div
                    x-show="open"
                    @click.outside="open = false"
                    x-transition.opacity.duration.200ms
                    class="absolute left-0 top-full mt-(--size-10) w-(--size-492) bg-white border border-gray-300 rounded-(--size-16) shadow-lg z-50"
                    x-cloak
                >
                    @foreach($cycleData as $item)
                        <button
                            type="button"
                            wire:click="selectCycle({{ $item->id }})"
                            @click="open = false"
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
        <div class="{{ $isSelectedCurrent ? 'inline-flex ' : 'hidden' }} gap-(--size-10) ">
            Ya
        </div>
    </div>
    <div class="overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) min-w-max w-full">
        <table class="w-full text-left border-collapse">
            <!-- Bagian Kepala (Header) -->
            <thead class="border-b-[1.5px] border-(--prime-light-colour) bg-(--prime-colour)">
                <tr>
                    <th class="min-w-[238px]">Tanggal</th>
                    <th class="min-w-[109px]">Fase</th>
                    <th class="min-w-[84px]">Suhu</th>
                    <th class="min-w-[96px]">Kelembapan</th>
                    <th class="min-w-[82px]">Berat Pakan</th>
                    <th class="min-w-[79px]">Berat Maggot</th>
                    <th class="border-r-0 min-w-[160px]">Aksi</th>
                </tr>
            </thead>

            <!-- Bagian Isi (Body) -->
            <tbody>
                @foreach($observationData as $item)
                <tr class="border-b-[1.5px] border-(--outline-colour) hover:bg-gray-50 transition-colors">
                    <td>{{ $item->timestamp->translatedFormat('l, d F Y') }}</td>
                    <td>{{ $item->phase_name}}</td>
                    <td>{{ $item->environmentLog->temperature ?? '-' }}&deg;C</td>
                    <td>{{ $item->environmentLog->humidity ?? '-' }}%</td>
                    <td>{{ $item->feed_weight}}kg</td>
                    <td>{{ $item->maggot_weight}}kg</td>
                    <td class="border-r-0">
                        <!-- Aksi menggunakan button agar interaktif -->
                        <button class="inline-flex items-center justify-center gap-(--size-10) text-(--text-colour) hover:underline">
                            <x-lucide-square-pen class="w-(--size-16)"/>
                            <span>Ubah Catatan</span>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

    </div>
</div>
