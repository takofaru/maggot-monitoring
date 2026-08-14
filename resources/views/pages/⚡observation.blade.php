<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Cycle;
use App\Models\ObservationLog;
use App\Models\EnvironmentLog;
use App\Models\PhaseSetting;
use Carbon\Carbon;

new class extends Component
{
    use WithPagination;

    public $selectedCycleId;
    public $selectedCycleName = '#';
    public $latestCycle;
    public $isSelectedCurrent = true;

    // State Modal Form
    public bool $openForm = false;
    public ?int $editingId = null;

    // Form Field Properties
    public $temp = '';
    public $humid = '';
    public bool $useManualEnvLog = false;
    public $feed = '';
    public $maggot = '';

    // Feedback message
    public string $flashMessage = '';

    public function mount()
    {
        $latest = Cycle::where('is_active', true)->first() ?? Cycle::latest('id')->first();
        if ($latest) {
            $this->latestCycle = $latest->id;
            $this->selectedCycleId = $latest->id;
            $this->selectedCycleName = "Siklus {$latest->id}";
            $this->isSelectedCurrent = (bool) $latest->is_active;
        }
    }

    public function selectCycle($id)
    {
        $this->selectedCycleId = $id;
        $this->selectedCycleName = "Siklus {$id}";
        $cycle = Cycle::find($id);
        $this->isSelectedCurrent = $cycle ? (bool) $cycle->is_active : false;
        $this->resetPage();
    }

    public function nextPhase()
    {
        if (!$this->selectedCycleId) return;

        $cycle = Cycle::find($this->selectedCycleId);
        if (!$cycle || !$cycle->is_active) return;

        $currentPhase = strtolower($cycle->current_phase);

        // Jika sudah di tahap prepupa: menyelesaikan siklus lama & otomatis membuat siklus baru
        if ($currentPhase === 'prepupa') {
            $cycle->update([
                'current_phase' => 'panen',
                'is_active'     => false,
                'end_date'      => now()->toDateString(),
            ]);

            // Buat siklus baru yang aktif dengan fase awal penetasan
            $newCycle = Cycle::create([
                'start_date'    => now()->toDateString(),
                'end_date'      => null,
                'current_phase' => 'penetasan',
                'is_active'     => true,
            ]);

            // Otomatis pindah ke siklus baru
            $this->latestCycle = $newCycle->id;
            $this->selectedCycleId = $newCycle->id;
            $this->selectedCycleName = "Siklus {$newCycle->id}";
            $this->isSelectedCurrent = true;
            $this->flashMessage = "Siklus {$cycle->id} telah selesai (Panen). Siklus baru (Siklus {$newCycle->id}) berhasil dimulai dengan fase Penetasan.";
            $this->resetPage();
            return;
        }

        // Transisi fase: penetasan -> pembesaran -> prepupa
        $phases = ['penetasan', 'pembesaran', 'prepupa'];
        $currentIndex = array_search($currentPhase, $phases);

        if ($currentIndex === false || $currentIndex === null) {
            $nextPhase = 'penetasan';
        } else {
            $nextIndex = min(count($phases) - 1, $currentIndex + 1);
            $nextPhase = $phases[$nextIndex];
        }

        $cycle->update(['current_phase' => $nextPhase]);
        $this->flashMessage = "Fase berhasil diubah menjadi " . ucfirst($nextPhase) . ".";
    }

    public function openCreateModal()
    {
        if (!$this->isSelectedCurrent) return;

        $this->resetForm();
        $this->useManualEnvLog = false;

        // Ambil data telemetri otomatis terkini dari siklus
        $latestEnv = EnvironmentLog::where('cycle_id', $this->selectedCycleId)->latest('id')->first();
        if ($latestEnv) {
            $this->temp = $latestEnv->temperature;
            $this->humid = $latestEnv->humidity;
        }

        $this->editingId = null;
        $this->openForm = true;
    }

    public function openEditModal($id)
    {
        $log = ObservationLog::with(['environmentLog', 'cycle'])->findOrFail($id);

        // Hanya catatan pada siklus aktif yang dapat diedit
        if (!$log->cycle?->is_active) {
            $this->flashMessage = 'Catatan pada siklus yang sudah selesai tidak dapat diubah.';
            return;
        }

        $this->editingId = $log->id;
        $this->feed = $log->feed_weight;
        $this->maggot = $log->maggot_weight;
        $this->temp = $log->environmentLog?->temperature ?? '';
        $this->humid = $log->environmentLog?->humidity ?? '';
        $this->useManualEnvLog = false;
        $this->openForm = true;
    }

    public function updatedUseManualEnvLog($value)
    {
        if (!$value) {
            // Ketika switch dimatikan, kembalikan ke data telemetri otomatis sensor terkini
            $latestEnv = EnvironmentLog::where('cycle_id', $this->selectedCycleId)->latest('id')->first();
            if ($latestEnv) {
                $this->temp = $latestEnv->temperature;
                $this->humid = $latestEnv->humidity;
            }
        }
    }

    public function closeForm()
    {
        $this->resetForm();
        $this->openForm = false;
    }

    public function resetForm()
    {
        $this->reset(['temp', 'humid', 'feed', 'maggot', 'useManualEnvLog', 'editingId']);
        $this->resetErrorBag();
    }

    public function save()
    {
        $this->validate([
            'feed'   => 'required|numeric|min:0',
            'maggot' => 'required|numeric|min:0',
            'temp'   => 'nullable|numeric|between:0,100',
            'humid'  => 'nullable|numeric|between:0,100',
        ], [
            'feed.required'   => 'Berat pakan wajib diisi.',
            'feed.numeric'    => 'Berat pakan harus berupa angka.',
            'feed.min'        => 'Berat pakan minimal 0 kg.',
            'maggot.required' => 'Berat maggot wajib diisi.',
            'maggot.numeric'  => 'Berat maggot harus berupa angka.',
            'maggot.min'      => 'Berat maggot minimal 0 kg.',
            'temp.numeric'    => 'Suhu harus berupa angka.',
            'temp.between'    => 'Suhu harus bernilai antara 0 - 100.',
            'humid.numeric'   => 'Kelembapan harus berupa angka.',
            'humid.between'   => 'Kelembapan harus bernilai antara 0 - 100.',
        ]);

        $cycle = Cycle::findOrFail($this->selectedCycleId);

        if (!$cycle->is_active) {
            $this->flashMessage = 'Tidak dapat menambah/mengubah catatan pada siklus yang sudah selesai.';
            $this->closeForm();
            return;
        }

        // Penanganan EnvironmentLog
        $envLogId = null;
        if ($this->useManualEnvLog) {
            // Pengguna memilih input manual: buat record environment log baru
            $envLog = EnvironmentLog::create([
                'cycle_id'    => $cycle->id,
                'timestamp'   => now(),
                'temperature' => (float) ($this->temp !== '' ? $this->temp : 28.00),
                'humidity'    => (float) ($this->humid !== '' ? $this->humid : 70.00),
            ]);
            $envLogId = $envLog->id;
        } else {
            // Otomatis: gunakan data environment log terakhir dari siklus
            $latestEnv = EnvironmentLog::where('cycle_id', $cycle->id)->latest('id')->first();
            if ($latestEnv) {
                $envLogId = $latestEnv->id;
            } else {
                $envLog = EnvironmentLog::create([
                    'cycle_id'    => $cycle->id,
                    'timestamp'   => now(),
                    'temperature' => (float) ($this->temp !== '' ? $this->temp : 28.00),
                    'humidity'    => (float) ($this->humid !== '' ? $this->humid : 70.00),
                ]);
                $envLogId = $envLog->id;
            }
        }

        // Tentukan fase (enum: penetasan, pembesaran, prepupa)
        $phaseName = in_array(strtolower($cycle->current_phase), ['penetasan', 'pembesaran', 'prepupa'])
            ? strtolower($cycle->current_phase)
            : 'prepupa';

        if ($this->editingId) {
            // Mode Update Catatan
            $log = ObservationLog::findOrFail($this->editingId);
            $log->update([
                'feed_weight'        => $this->feed,
                'maggot_weight'      => $this->maggot,
                'environment_log_id' => $envLogId,
            ]);
            $this->flashMessage = 'Catatan observasi berhasil diperbarui.';
        } else {
            // Mode Tambah Catatan Baru
            ObservationLog::create([
                'cycle_id'           => $cycle->id,
                'phase_name'         => $phaseName,
                'environment_log_id' => $envLogId,
                'timestamp'          => now()->toDateString(),
                'feed_weight'        => $this->feed,
                'maggot_weight'      => $this->maggot,
            ]);
            $this->flashMessage = 'Catatan observasi baru berhasil ditambahkan.';
        }

        $this->closeForm();
        $this->resetPage();
    }

    public function deleteObservationLog($id)
    {
        $log = ObservationLog::with('cycle')->find($id);
        if ($log) {
            if (!$log->cycle?->is_active) {
                $this->flashMessage = 'Catatan pada siklus yang sudah selesai tidak dapat dihapus.';
                return;
            }
            $log->delete();
            $this->flashMessage = 'Catatan observasi berhasil dihapus.';
        }
    }

    public function with(): array
    {
        return [
            'cycleData' => Cycle::orderBy('id', 'asc')->get(),
            'observationData' => ObservationLog::with(['environmentLog', 'cycle'])
                ->where('cycle_id', $this->selectedCycleId)
                ->orderBy('timestamp', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(10),
        ];
    }
};
?>

<div class="space-y-(--size-26) min-w-[922px]">
    <!-- Judul & Flash Notification -->
    <div class="flex items-center justify-between">
        <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold">
            Catatan Observasi
        </h1>
        @if ($flashMessage)
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 4500)"
                class="flex items-center gap-2 px-4 py-2 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl text-xs font-semibold shadow-sm transition-all"
            >
                <x-lucide-check-circle class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ $flashMessage }}</span>
            </div>
        @endif
    </div>

    <!-- Toolbar: Selector Siklus, Fase Terkini, & Tombol Tambah -->
    <div class="inline-flex gap-(--size-10) justify-between w-full">
        <div class="flex flex-row items-center gap-(--size-10)">
            <!-- Dropdown Pilihan Siklus -->
            <div x-data="{ openDropdown: false }" class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px]">
                Siklus ke:
                <div class="relative inline-block">
                    <button
                        @click="openDropdown = !openDropdown"
                        type="button"
                        class="rounded-(--size-16) inline-flex justify-between items-center gap-(--size-10) input-text text-(--size-16) hover:bg-(--bg2-colour) cursor-pointer"
                    >
                        <span>{{ $selectedCycleName }}</span>
                        <x-lucide-chevron-down class="w-(--size-16)"/>
                    </button>

                    <div
                        x-show="openDropdown"
                        @click.outside="openDropdown = false"
                        x-transition.opacity.duration.200ms
                        class="absolute left-0 top-full mt-(--size-10) w-(--size-492) bg-white border border-gray-300 rounded-(--size-16) shadow-xl z-50 max-h-72 overflow-y-auto"
                        x-cloak
                    >
                        @foreach($cycleData as $item)
                            <button
                                type="button"
                                wire:click="selectCycle({{ $item->id }})"
                                @click="openDropdown = false"
                                class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 rounded border-b border-gray-100 last:border-0 cursor-pointer {{ $item->id == $selectedCycleId ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
                            >
                                <span class="font-semibold flex items-center gap-2">
                                    Siklus {{ $item->id }}
                                    @if($item->is_active)
                                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-[11px]">Aktif</span>
                                    @else
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-[11px]">Selesai</span>
                                    @endif
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ $item->start_date ? $item->start_date->translatedFormat('d M Y') : '-' }} &mdash; {{ $item->end_date ? $item->end_date->translatedFormat('d M Y') : 'Sekarang' }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Fase Terkini & Tombol Ganti Fase dengan Konfirmasi -->
            @php
                $activeCycleObj = $cycleData->firstWhere('id', $selectedCycleId);
                $currPhase = strtolower($activeCycleObj->current_phase ?? '');
                $confirmMsg = match($currPhase) {
                    'penetasan' => 'Yakin ingin melanjutkan ke fase Pembesaran?',
                    'pembesaran' => 'Yakin ingin melanjutkan ke fase Prepupa?',
                    'prepupa'   => 'Siklus berada pada tahap Prepupa. Melanjutkan akan menyelesaikan siklus ini (Panen) dan otomatis membuat siklus baru. Lanjutkan?',
                    default     => 'Yakin ingin melanjutkan ke fase berikutnya?'
                };
            @endphp
            <div class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] h-full">
                <div class="gap-(--size-6)">
                    Fase terkini:
                    <span class="font-bold text-(--prime-colour) capitalize">{{ $activeCycleObj->current_phase ?? '-' }}</span>
                </div>
                @if($isSelectedCurrent && $currPhase !== 'panen')
                    <button
                        wire:click="nextPhase"
                        wire:confirm="{{ $confirmMsg }}"
                        type="button"
                        title="{{ $currPhase === 'prepupa' ? 'Selesaikan siklus ini dan mulai siklus baru' : 'Lanjut ke fase berikutnya' }}"
                        class="rounded-(--size-16) inline-flex justify-between items-center gap-(--size-10) px-(--size-16) py-(--size-6) input-button text-(--fg-colour) cursor-pointer hover:opacity-90"
                    >
                        <x-lucide-chevrons-right class="w-(--size-26)"/>
                    </button>
                @endif
            </div>
        </div>

        <!-- Tombol Tambah Catatan Baru (Hanya untuk Siklus Aktif) -->
        @if($isSelectedCurrent)
            <button
                wire:click="openCreateModal"
                type="button"
                class="gap-(--size-10) input-button cursor-pointer hover:opacity-90 flex items-center"
            >
                <x-lucide-plus class="w-(--size-26)"/>
                <span>Tambah Catatan Baru</span>
            </button>
        @endif
    </div>

    <!-- Tabel Catatan Observasi -->
    <div class="overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) min-w-max w-full shadow-xs">
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
                @forelse($observationData as $item)
                    <tr class="border-b-[1.5px] border-(--outline-colour) hover:bg-gray-50 transition-colors">
                        <td>{{ $item->timestamp ? $item->timestamp->translatedFormat('l, d F Y') : '-' }}</td>
                        <td>
                            <span class="px-2.5 py-1 bg-gray-100 text-gray-800 rounded-md font-medium text-xs capitalize">
                                {{ $item->phase_name }}
                            </span>
                        </td>
                        <td>{{ $item->environmentLog->temperature ?? '-' }}&deg;C</td>
                        <td>{{ $item->environmentLog->humidity ?? '-' }}%</td>
                        <td>{{ $item->feed_weight }} kg</td>
                        <td>{{ $item->maggot_weight }} kg</td>
                        <td class="border-r-0 flex flex-row gap-(--size-10) w-full justify-center py-2">
                            @if($isSelectedCurrent)
                                <button
                                    wire:click="openEditModal({{ $item->id }})"
                                    type="button"
                                    title="Ubah Catatan"
                                    class="input-button p-(--size-10) cursor-pointer hover:bg-(--prime-light-colour)"
                                >
                                    <x-lucide-square-pen class="w-(--size-16)"/>
                                </button>
                                <button
                                    wire:click="deleteObservationLog({{ $item->id }})"
                                    wire:confirm="Yakin ingin menghapus catatan observasi ini?"
                                    type="button"
                                    title="Hapus Catatan"
                                    class="input-button p-(--size-10) bg-red-600 hover:bg-red-700 cursor-pointer"
                                >
                                    <x-lucide-trash-2 class="w-(--size-16)"/>
                                </button>
                            @else
                                <button
                                    type="button"
                                    disabled
                                    title="Siklus sudah selesai (tidak dapat diubah)"
                                    class="input-button p-(--size-10) opacity-30 cursor-not-allowed grayscale pointer-events-none"
                                >
                                    <x-lucide-square-pen class="w-(--size-16)"/>
                                </button>
                                <button
                                    type="button"
                                    disabled
                                    title="Siklus sudah selesai (tidak dapat dihapus)"
                                    class="input-button p-(--size-10) opacity-30 cursor-not-allowed grayscale bg-red-600 pointer-events-none"
                                >
                                    <x-lucide-trash-2 class="w-(--size-16)"/>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-400">
                            Belum ada catatan observasi untuk siklus ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="m-0">
        {{ $observationData->links() }}
    </div>

    <!-- Modal Form Observasi (Create & Edit) -->
    @if ($openForm)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4"
            x-transition.opacity
        >
            <div
                @click.outside="$wire.closeForm()"
                class="w-full max-w-(--size-492) bg-(--fg-colour) rounded-(--size-16) p-(--size-26) border-[1.5px] border-(--outline-colour) shadow-2xl space-y-(--size-26) max-h-[90vh] overflow-y-auto"
            >
                <form wire:submit="save" class="flex flex-col gap-(--size-26)">
                    <!-- Header Modal -->
                    <div class="flex items-center justify-between">
                        <span class="text-(length:--size-26) text-(--prime-colour) font-bold">
                            {{ $editingId ? 'Ubah Catatan Observasi' : 'Tambah Catatan Baru' }}
                        </span>
                        <button
                            type="button"
                            wire:click="closeForm"
                            class="text-gray-400 hover:text-gray-600 cursor-pointer text-xl font-bold"
                        >
                            &times;
                        </button>
                    </div>

                    <!-- Input Suhu & Kelembapan (Enabled/Disabled berdasarkan Switch) -->
                    <div class="flex flex-row gap-(--size-16)">
                        <div class="input-container w-full">
                            <label for="temp">Suhu yang Diamati</label>
                            <div class="flex flex-row items-center justify-between input-text {{ !$useManualEnvLog ? 'bg-gray-100 text-gray-500' : '' }} @error('temp') border-red-500 @enderror">
                                <input
                                    wire:model="temp"
                                    id="temp"
                                    type="number"
                                    step="0.01"
                                    placeholder="Contoh: 28.5"
                                    @disabled(!$useManualEnvLog)
                                    class="w-full bg-transparent focus:outline-none disabled:opacity-75 disabled:cursor-not-allowed"
                                />
                                <span class="text-gray-500 font-medium">&deg;C</span>
                            </div>
                            @error('temp')
                                <span class="text-xs text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="input-container w-full">
                            <label for="humid">Kelembapan yang Diamati</label>
                            <div class="flex flex-row items-center justify-between input-text {{ !$useManualEnvLog ? 'bg-gray-100 text-gray-500' : '' }} @error('humid') border-red-500 @enderror">
                                <input
                                    wire:model="humid"
                                    id="humid"
                                    type="number"
                                    step="0.01"
                                    placeholder="Contoh: 70"
                                    @disabled(!$useManualEnvLog)
                                    class="w-full bg-transparent focus:outline-none disabled:opacity-75 disabled:cursor-not-allowed"
                                />
                                <span class="text-gray-500 font-medium">%</span>
                            </div>
                            @error('humid')
                                <span class="text-xs text-red-500">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <!-- Switch Penggunaan Data Manual -->
                    <div class="flex flex-row gap-(--size-10) items-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                wire:model.live="useManualEnvLog"
                                type="checkbox"
                                class="sr-only peer"
                            />
                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-(--prime-colour)"></div>
                        </label>
                        <span class="text-sm font-medium text-gray-700">Menggunakan data suhu dan kelembapan secara manual</span>
                    </div>

                    <!-- Input Berat Pakan & Maggot -->
                    <div class="flex flex-row gap-(--size-16)">
                        <div class="input-container w-full">
                            <label for="feed">Berat Pakan yang Diberikan</label>
                            <div class="flex flex-row items-center justify-between input-text @error('feed') border-red-500 @enderror">
                                <input
                                    wire:model="feed"
                                    id="feed"
                                    type="number"
                                    step="0.01"
                                    placeholder="Contoh: 5.5"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                <span class="text-gray-500 font-medium">kg</span>
                            </div>
                            @error('feed')
                                <span class="text-xs text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="input-container w-full">
                            <label for="maggot">Berat Maggot yang Diamati</label>
                            <div class="flex flex-row items-center justify-between input-text @error('maggot') border-red-500 @enderror">
                                <input
                                    wire:model="maggot"
                                    id="maggot"
                                    type="number"
                                    step="0.01"
                                    placeholder="Contoh: 1.2"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                <span class="text-gray-500 font-medium">kg</span>
                            </div>
                            @error('maggot')
                                <span class="text-xs text-red-500">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <!-- Tombol Simpan & Batal -->
                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="closeForm"
                            class="px-4 py-2 border border-gray-300 rounded-(--size-16) text-gray-700 font-medium hover:bg-gray-100 cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="input-button cursor-pointer flex items-center gap-2"
                        >
                            @if ($editingId)
                                <x-lucide-square-pen class="w-(--size-16)"/>
                                <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                            @else
                                <x-lucide-plus class="w-(--size-16)"/>
                                <span wire:loading.remove wire:target="save">Tambah Catatan</span>
                            @endif
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
