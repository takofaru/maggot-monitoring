<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Cycle;
use App\Models\ObservationLog;
use App\Models\EnvironmentLog;
use App\Models\PhaseSetting;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use WithPagination;

    public $selectedCycleId;
    public $selectedCycleName = '#';
    public $isSelectedCurrent = true;

    public function mount()
    {
        $latest = Cycle::where('is_active', true)->first() ?? Cycle::latest('id')->first();
        if ($latest) {
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

    public function exportCsv(): StreamedResponse
    {
        $cycleId = $this->selectedCycleId;
        $cycle = Cycle::find($cycleId);
        $logs = ObservationLog::with('environmentLog')
            ->where('cycle_id', $cycleId)
            ->orderBy('timestamp', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $filename = "laporan_siklus_{$cycleId}_" . now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($logs, $cycle) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM untuk kompatibilitas Excel
            fputs($handle, "\xEF\xBB\xBF");

            // Header Informasi Siklus
            fputcsv($handle, ["LAPORAN REKAPITULASI BUDIDAYA MAGGOT - SIKLUS {$cycle?->id}"], ',', '"', "\\");
            fputcsv($handle, ["Tanggal Mulai", $cycle?->start_date?->format('d/m/Y') ?? '-'], ',', '"', "\\");
            fputcsv($handle, ["Tanggal Selesai", $cycle?->end_date?->format('d/m/Y') ?? 'Sedang Berjalan'], ',', '"', "\\");
            fputcsv($handle, ["Status", $cycle?->is_active ? 'Aktif' : 'Panen / Selesai'], ',', '"', "\\");
            fputcsv($handle, [], ',', '"', "\\");

            // Header Tabel Data
            fputcsv($handle, [
                'No',
                'Tanggal',
                'Fase',
                'Suhu (°C)',
                'Kelembapan (%)',
                'Pakan Diberikan (kg)',
                'Berat Maggot (kg)',
            ], ',', '"', "\\");

            foreach ($logs as $index => $log) {
                fputcsv($handle, [
                    $index + 1,
                    $log->timestamp ? $log->timestamp->format('d/m/Y') : '-',
                    ucfirst($log->phase_name),
                    $log->environmentLog?->temperature ?? '-',
                    $log->environmentLog?->humidity ?? '-',
                    number_format((float) $log->feed_weight, 2, '.', ''),
                    number_format((float) $log->maggot_weight, 2, '.', ''),
                ], ',', '"', "\\");
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function with(): array
    {
        $cycle = Cycle::find($this->selectedCycleId);

        // Seluruh observasi pada siklus ini (urutan waktu untuk kalkulasi KPI)
        $allLogs = ObservationLog::with('environmentLog')
            ->where('cycle_id', $this->selectedCycleId)
            ->orderBy('timestamp', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Seluruh log lingkungan pada siklus ini
        $envLogs = EnvironmentLog::where('cycle_id', $this->selectedCycleId)->get();

        // 1. Perhitungan KPI
        $totalFeed = (float) $allLogs->sum('feed_weight');
        $finalMaggotWeight = (float) ($allLogs->last()?->maggot_weight ?? 0.0);
        $initialMaggotWeight = (float) ($allLogs->first()?->maggot_weight ?? 0.0);
        $netMaggotGain = max(0, $finalMaggotWeight - $initialMaggotWeight);

        // FCR = Total Pakan / Berat Maggot Akhir
        $fcr = $finalMaggotWeight > 0 ? round($totalFeed / $finalMaggotWeight, 2) : 0.0;

        // Durasi Hari Siklus
        $durationDays = 0;
        if ($cycle && $cycle->start_date) {
            $endDate = $cycle->end_date ?? now();
            $durationDays = (int) Carbon::parse($cycle->start_date)->diffInDays(Carbon::parse($endDate)) + 1;
        }

        // Rata-rata Lingkungan
        $avgTemp = $envLogs->count() > 0 ? round((float) $envLogs->avg('temperature'), 1) : 0.0;
        $avgHumid = $envLogs->count() > 0 ? round((float) $envLogs->avg('humidity'), 1) : 0.0;

        // 2. Perhitungan Performa Per Fase
        $phaseBreakdown = [];
        $phases = ['penetasan', 'pembesaran', 'prepupa'];
        $phaseSettings = PhaseSetting::all()->keyBy('phase_name');

        foreach ($phases as $pName) {
            $pLogs = $allLogs->where('phase_name', $pName);
            $pFeed = (float) $pLogs->sum('feed_weight');
            $pStartMaggot = (float) ($pLogs->first()?->maggot_weight ?? 0.0);
            $pEndMaggot = (float) ($pLogs->last()?->maggot_weight ?? 0.0);
            $pGain = max(0, $pEndMaggot - $pStartMaggot);

            $pEnvIds = $pLogs->pluck('environment_log_id')->filter();
            $pEnvLogs = $envLogs->whereIn('id', $pEnvIds);
            $pAvgTemp = $pEnvLogs->count() > 0 ? round((float) $pEnvLogs->avg('temperature'), 1) : '-';
            $pAvgHumid = $pEnvLogs->count() > 0 ? round((float) $pEnvLogs->avg('humidity'), 1) : '-';

            $phaseBreakdown[$pName] = [
                'name'         => ucfirst($pName),
                'log_count'    => $pLogs->count(),
                'total_feed'   => $pFeed,
                'end_maggot'   => $pEndMaggot,
                'growth_gain'  => $pGain,
                'avg_temp'     => $pAvgTemp,
                'avg_humid'    => $pAvgHumid,
                'ideal_temp'   => isset($phaseSettings[$pName]) ? "{$phaseSettings[$pName]->temp_bottom}° - {$phaseSettings[$pName]->temp_top}°C" : '-',
                'ideal_humid'  => isset($phaseSettings[$pName]) ? "{$phaseSettings[$pName]->humid_bottom}% - {$phaseSettings[$pName]->humid_top}%" : '-',
            ];
        }

        return [
            'cycleData'        => Cycle::orderBy('id', 'asc')->get(),
            'currentCycle'     => $cycle,
            'totalFeed'        => $totalFeed,
            'finalMaggotWeight'=> $finalMaggotWeight,
            'fcr'              => $fcr,
            'durationDays'     => $durationDays,
            'avgTemp'          => $avgTemp,
            'avgHumid'         => $avgHumid,
            'phaseBreakdown'   => $phaseBreakdown,
            'observationLogs'  => ObservationLog::with(['environmentLog', 'cycle'])
                ->where('cycle_id', $this->selectedCycleId)
                ->orderBy('timestamp', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(10),
        ];
    }
};
?>

<div class="space-y-(--size-26) w-full max-w-full">
    <!-- Header Halaman -->
    <div class="flex items-center justify-between">
        <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold leading-tight">
            Laporan Budidaya
        </h1>
    </div>

    <!-- Toolbar: Selector Siklus, Status Siklus, & Tombol Aksi (Harmonized with observation.blade.php) -->
    <div class="inline-flex gap-(--size-10) justify-between w-full flex-wrap items-center">
        <div class="flex flex-row items-center gap-(--size-10) flex-wrap">
            <!-- Dropdown Pilihan Siklus -->
            <div x-data="{ openDropdown: false }" class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs whitespace-nowrap shrink-0">
                <span>Siklus ke:</span>
                <div class="relative inline-block">
                    <button
                        @click="openDropdown = !openDropdown"
                        type="button"
                        class="rounded-(--size-16) inline-flex justify-between items-center gap-(--size-10) input-text text-(--size-16) hover:bg-(--bg2-colour) cursor-pointer whitespace-nowrap shrink-0"
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
                                class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 border-b border-gray-100 last:border-0 cursor-pointer {{ $item->id == $selectedCycleId ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
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

            <!-- Status Siklus Pill -->
            <div class="inline-flex gap-(--size-10) items-center px-(--size-16) py-(--size-10) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs text-sm whitespace-nowrap shrink-0">
                <div class="gap-(--size-6)">
                    Status:
                    <span class="font-bold text-(--prime-colour)">
                        {{ $currentCycle?->is_active ? 'Aktif (' . ucfirst($currentCycle->current_phase) . ')' : 'Selesai / Panen' }}
                    </span>
                    <span class="text-xs text-gray-400 ml-1">({{ $durationDays }} Hari)</span>
                </div>
            </div>
        </div>

        <!-- Tombol Ekspor CSV & Cetak -->
        <div class="flex flex-row items-center gap-(--size-10) flex-wrap">
            <button
                wire:click="exportCsv"
                type="button"
                class="gap-(--size-10) input-button cursor-pointer hover:opacity-90 flex items-center whitespace-nowrap shrink-0"
            >
                <x-lucide-download class="w-(--size-26)"/>
                <span>Ekspor CSV</span>
            </button>

            <button
                onclick="window.print()"
                type="button"
                class="gap-(--size-10) input-button cursor-pointer hover:opacity-90 flex items-center whitespace-nowrap shrink-0"
            >
                <x-lucide-printer class="w-(--size-26)"/>
                <span>Cetak Laporan</span>
            </button>
        </div>
    </div>

    <!-- 3 Kartu Ringkasan KPI Utama (Harmonized with dashboard.blade.php) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-(--size-26) w-full">
        <!-- 1. Total Pakan Kumulatif -->
        <div class="flex flex-col justify-between gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex flex-row items-center gap-(--size-16)">
                <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                    <x-lucide-apple class="w-(--size-26) h-(--size-26)"/>
                </div>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                    Total Pakan Kumulatif
                </span>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-(length:--size-42) font-extrabold text-(--prime-colour) leading-none">
                        {{ number_format($totalFeed, 1) }}
                    </span>
                    <span class="text-base font-bold text-gray-500">kg</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Akumulasi konsumsi pakan siklus
                </p>
            </div>
        </div>

        <!-- 2. Hasil Akhir Maggot -->
        <div class="flex flex-col justify-between gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex flex-row items-center gap-(--size-16)">
                <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                    <x-lucide-weight class="w-(--size-26) h-(--size-26)"/>
                </div>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                    Hasil Akhir Maggot
                </span>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-(length:--size-42) font-extrabold text-(--prime-colour) leading-none">
                        {{ number_format($finalMaggotWeight, 1) }}
                    </span>
                    <span class="text-base font-bold text-gray-500">kg</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Biomassa maggot tercatat
                </p>
            </div>
        </div>

        <!-- 3. Konversi Rasio Pakan (FCR) -->
        <div class="flex flex-col justify-between gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex flex-row items-center gap-(--size-16)">
                <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                    <x-lucide-ruler class="w-(--size-26) h-(--size-26)"/>
                </div>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                    Konversi Rasio Pakan (FCR)
                </span>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-(length:--size-42) font-extrabold text-(--prime-colour) leading-none">
                        {{ $fcr > 0 ? number_format($fcr, 1) : '-' }}
                    </span>
                    <span class="text-xs font-semibold text-gray-500">per kg maggot</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Rata-rata lingkungan: {{ $avgTemp }}&deg;C &bull; {{ $avgHumid }}%
                </p>
            </div>
        </div>
    </div>

    <!-- Tabel 1: Analisis Performa Per Fase Budidaya -->
    <div class="flex flex-col gap-(--size-16) px-(--size-26) py-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
        <div class="flex flex-row gap-(--size-16) items-center">
            <x-lucide-bar-chart-3 class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
            <div>
                <h2 class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                    Analisis Performa Per Fase Budidaya
                </h2>
            </div>
        </div>

        <div class="overflow-x-auto border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) w-full shadow-xs mt-2">
            <table class="w-full text-left border-collapse min-w-full">
                <thead class="border-b-[1.5px] border-(--prime-light-colour) bg-(--prime-colour)">
                    <tr>
                        <th class="min-w-[140px]">Fase Budidaya</th>
                        <th class="min-w-[100px]">Jumlah Log</th>
                        <th class="min-w-[130px]">Total Pakan</th>
                        <th class="min-w-[150px]">Bobot Maggot Fase</th>
                        <th class="min-w-[130px]">Suhu Aktual (Ideal)</th>
                        <th class="border-r-0 min-w-[150px]">Kelembapan Aktual (Ideal)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($phaseBreakdown as $key => $p)
                        <tr class="border-b-[1.5px] border-(--outline-colour) hover:bg-gray-50 transition-colors">
                            <td class="font-bold text-(--prime-colour)">
                                <div class="flex items-center justify-center gap-2">
                                    @if($key === 'penetasan')
                                        <x-lucide-egg class="w-4 h-4 text-(--prime-colour)" />
                                    @elseif($key === 'pembesaran')
                                        <x-lucide-worm class="w-4 h-4 text-(--prime-colour)" />
                                    @else
                                        <x-lucide-bug class="w-4 h-4 text-(--prime-colour)" />
                                    @endif
                                    <span>{{ $p['name'] }}</span>
                                </div>
                            </td>
                            <td>{{ $p['log_count'] }} kali observasi</td>
                            <td class="font-semibold">{{ number_format($p['total_feed'], 1) }} kg</td>
                            <td>
                                <div class="font-semibold text-gray-900">{{ number_format($p['end_maggot'], 1) }} kg</div>
                                @if($p['growth_gain'] > 0)
                                    <div class="text-[11px] text-emerald-700 font-medium">(+{{ number_format($p['growth_gain'], 1) }} kg pada fase ini)</div>
                                @endif
                            </td>
                            <td>
                                <span class="font-semibold">{{ $p['avg_temp'] !== '-' ? $p['avg_temp'] . '°C' : '-' }}</span>
                                <span class="text-xs text-gray-400 block">({{ $p['ideal_temp'] }})</span>
                            </td>
                            <td class="border-r-0">
                                <span class="font-semibold">{{ $p['avg_humid'] !== '-' ? $p['avg_humid'] . '%' : '-' }}</span>
                                <span class="text-xs text-gray-400 block">({{ $p['ideal_humid'] }})</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabel 2: Rincian Log Harian Siklus -->
    <div class="flex flex-col gap-(--size-16) px-(--size-26) py-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
        <div class="flex flex-row gap-(--size-16) items-center">
            <x-lucide-table class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
            <div>
                <h2 class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                    Rincian Log Harian Siklus
                </h2>
            </div>
        </div>

        <div class="overflow-x-auto border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) w-full shadow-xs mt-2">
            <table class="w-full text-left border-collapse min-w-full">
                <thead class="border-b-[1.5px] border-(--prime-light-colour) bg-(--prime-colour)">
                    <tr>
                        <th class="min-w-[200px]">Tanggal</th>
                        <th class="min-w-[110px]">Fase</th>
                        <th class="min-w-[90px]">Suhu</th>
                        <th class="min-w-[100px]">Kelembapan</th>
                        <th class="min-w-[120px]">Pakan Diberikan</th>
                        <th class="border-r-0 min-w-[120px]">Berat Maggot</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($observationLogs as $item)
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
                            <td class="border-r-0">{{ $item->maggot_weight }} kg</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="border-r-0 py-8 text-center text-gray-400">
                                Tidak ada catatan observasi untuk siklus ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
        </div>

        @if($observationLogs->hasPages())
            <div class="pt-2">
                {{ $observationLogs->links() }}
            </div>
        @endif
    </div>
</div>