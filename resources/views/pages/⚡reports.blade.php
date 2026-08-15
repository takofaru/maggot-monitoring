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

    // Mode Laporan: 'periodic' (Laporan Periodik) atau 'cycle' (Laporan Siklus)
    public string $reportMode = 'periodic';

    // Filter Mode Siklus
    public $selectedCycleId;
    public $selectedCycleName = '#';
    public $isSelectedCurrent = true;

    // Filter Mode Periodik
    public string $periodicPreset = '30days'; // 'today', '7days', '30days', 'this_month', 'custom'
    public string $startDate = '';
    public string $endDate = '';

    public function mount()
    {
        // Inisialisasi default filter periodik (30 hari terakhir)
        $this->endDate = now()->toDateString();
        $this->startDate = now()->subDays(29)->toDateString();

        // Inisialisasi default filter siklus
        $latest = Cycle::where('is_active', true)->first() ?? Cycle::latest('id')->first();
        if ($latest) {
            $this->selectedCycleId = $latest->id;
            $this->selectedCycleName = "Siklus {$latest->id}";
            $this->isSelectedCurrent = (bool) $latest->is_active;
        }
    }

    public function setReportMode(string $mode)
    {
        $this->reportMode = $mode;
        $this->resetPage();
    }

    public function setPeriodicPreset(string $preset)
    {
        $this->periodicPreset = $preset;
        $today = now();

        match ($preset) {
            'today' => [
                $this->startDate = $today->toDateString(),
                $this->endDate = $today->toDateString(),
            ],
            '7days' => [
                $this->startDate = $today->copy()->subDays(6)->toDateString(),
                $this->endDate = $today->toDateString(),
            ],
            '30days' => [
                $this->startDate = $today->copy()->subDays(29)->toDateString(),
                $this->endDate = $today->toDateString(),
            ],
            'this_month' => [
                $this->startDate = $today->copy()->startOfMonth()->toDateString(),
                $this->endDate = $today->toDateString(),
            ],
            default => null,
        };

        $this->resetPage();
    }

    public function updatedStartDate()
    {
        $this->periodicPreset = 'custom';
        $this->resetPage();
    }

    public function updatedEndDate()
    {
        $this->periodicPreset = 'custom';
        $this->resetPage();
    }

    public function selectCycle($id)
    {
        $this->selectedCycleId = $id;
        $this->selectedCycleName = "Siklus {$id}";
        $cycle = Cycle::find($id);
        $this->isSelectedCurrent = $cycle ? (bool) $cycle->is_active : false;
        $this->resetPage();
    }

    public function getChartData(): array
    {
        if ($this->reportMode === 'periodic') {
            $start = Carbon::parse($this->startDate ?: now()->subDays(30))->startOfDay();
            $end = Carbon::parse($this->endDate ?: now())->endOfDay();

            $allLogs = ObservationLog::with('environmentLog')
                ->whereBetween('timestamp', [$start, $end])
                ->orderBy('timestamp', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        } else {
            $allLogs = ObservationLog::with('environmentLog')
                ->where('cycle_id', $this->selectedCycleId)
                ->orderBy('timestamp', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        $chartLabels = [];
        $chartMaggot = [];
        $chartFeed   = [];
        $chartTemp   = [];
        $chartHumid  = [];

        foreach ($allLogs as $log) {
            $chartLabels[] = $log->timestamp ? $log->timestamp->format('d M') : "#{$log->id}";
            $chartMaggot[] = (float) $log->maggot_weight;
            $chartFeed[]   = (float) $log->feed_weight;
            $chartTemp[]   = $log->environmentLog ? (float) $log->environmentLog->temperature : null;
            $chartHumid[]  = $log->environmentLog ? (float) $log->environmentLog->humidity : null;
        }

        return [
            'labels' => $chartLabels,
            'maggot' => $chartMaggot,
            'feed'   => $chartFeed,
            'temp'   => $chartTemp,
            'humid'  => $chartHumid,
        ];
    }

    public function rendering()
    {
        $chartData = $this->getChartData();
        $this->dispatch('report-charts-updated', ...$chartData);
    }

    public function exportCsv(): StreamedResponse
    {
        if ($this->reportMode === 'periodic') {
            $start = Carbon::parse($this->startDate ?: now()->subDays(30))->startOfDay();
            $end = Carbon::parse($this->endDate ?: now())->endOfDay();

            $logs = ObservationLog::with(['environmentLog', 'cycle'])
                ->whereBetween('timestamp', [$start, $end])
                ->orderBy('timestamp', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $filename = "laporan_periodik_" . $start->format('Ymd') . "_sd_" . $end->format('Ymd') . ".csv";

            // Ekspor data mentah murni (pure raw data) langsung dari baris header kolom
            return response()->streamDownload(function () use ($logs) {
                $handle = fopen('php://output', 'w');
                fputs($handle, "\xEF\xBB\xBF");

                fputcsv($handle, [
                    'No',
                    'Tanggal',
                    'Siklus',
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
                        $log->cycle ? "Siklus {$log->cycle->id}" : '-',
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

        // Export Mode Siklus
        $cycleId = $this->selectedCycleId;
        $logs = ObservationLog::with('environmentLog')
            ->where('cycle_id', $cycleId)
            ->orderBy('timestamp', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $filename = "laporan_siklus_{$cycleId}_" . now()->format('Ymd_His') . ".csv";

        // Ekspor data mentah murni (pure raw data) langsung dari baris header kolom
        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

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
        $phaseSettings = PhaseSetting::all()->keyBy('phase_name');
        $chartData = $this->getChartData();

        if ($this->reportMode === 'periodic') {
            $start = Carbon::parse($this->startDate ?: now()->subDays(30))->startOfDay();
            $end = Carbon::parse($this->endDate ?: now())->endOfDay();

            $allLogs = ObservationLog::with(['environmentLog', 'cycle'])
                ->whereBetween('timestamp', [$start, $end])
                ->orderBy('timestamp', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $envLogs = EnvironmentLog::whereBetween('timestamp', [$start, $end])->get();

            $totalFeed = (float) $allLogs->sum('feed_weight');
            $finalMaggotWeight = (float) ($allLogs->last()?->maggot_weight ?? 0.0);
            $initialMaggotWeight = (float) ($allLogs->first()?->maggot_weight ?? 0.0);
            $netMaggotGain = max(0, $finalMaggotWeight - $initialMaggotWeight);

            $fcr = $finalMaggotWeight > 0 ? round($totalFeed / $finalMaggotWeight, 2) : 0.0;
            $durationDays = (int) $start->diffInDays($end) + 1;

            $avgTemp = $envLogs->count() > 0 ? round((float) $envLogs->avg('temperature'), 1) : 0.0;
            $avgHumid = $envLogs->count() > 0 ? round((float) $envLogs->avg('humidity'), 1) : 0.0;

            // Hitung siklus yang terjadi pada rentang waktu ini (Logika Presisi: Selesai Penuh vs Separuh)
            $allCycles = Cycle::all();
            $logCycleIds = $allLogs->pluck('cycle_id')->filter()->unique();
            $involvedCycleIds = collect();

            foreach ($allCycles as $c) {
                if (!$c->start_date) continue;
                $cStart = Carbon::parse($c->start_date)->startOfDay();
                $cEnd = $c->end_date ? Carbon::parse($c->end_date)->endOfDay() : null;
                $effectiveEnd = $cEnd ?? now()->endOfDay();

                if ($cStart <= $end && $effectiveEnd >= $start) {
                    $involvedCycleIds->push($c->id);
                }
            }

            foreach ($logCycleIds as $cid) {
                $involvedCycleIds->push($cid);
            }

            $involvedCycles = Cycle::whereIn('id', $involvedCycleIds->unique())->orderBy('id', 'asc')->get();

            $completedCycles = [];
            $partialCycles = [];

            foreach ($involvedCycles as $c) {
                $cStart = $c->start_date ? Carbon::parse($c->start_date)->startOfDay() : null;
                $cEnd = $c->end_date ? Carbon::parse($c->end_date)->endOfDay() : null;

                // Definisi Siklus Selesai Penuh dalam Periode:
                // Dimulai pada/setelah batas awal periode DAN Selesai/Panen pada/sebelum batas akhir periode
                $isFullCompleted = ($cStart !== null && $cStart >= $start && $cEnd !== null && $cEnd <= $end && (!$c->is_active || $c->current_phase === 'panen'));

                if ($isFullCompleted) {
                    $completedCycles[] = [
                        'id'          => $c->id,
                        'name'        => "Siklus {$c->id}",
                        'type'        => 'completed',
                        'label'       => 'Selesai Penuh (Panen)',
                        'detail'      => 'Dimulai & Selesai di dalam periode ini',
                        'start_date'  => $c->start_date ? $c->start_date->translatedFormat('d M Y') : '-',
                        'end_date'    => $c->end_date ? $c->end_date->translatedFormat('d M Y') : '-',
                        'phase'       => 'Panen',
                    ];
                } else {
                    $reason = 'Sebagian Periode';
                    if ($cStart !== null && $cStart < $start && $cEnd !== null && $cEnd <= $end) {
                        $reason = 'Dimulai sebelum periode & selesai di periode ini';
                    } elseif ($cStart !== null && $cStart >= $start && ($cEnd === null || $cEnd > $end || $c->is_active)) {
                        $reason = 'Dimulai di periode ini & sedang berjalan (' . ucfirst($c->current_phase) . ')';
                    } elseif ($cStart !== null && $cStart < $start && ($cEnd === null || $cEnd > $end || $c->is_active)) {
                        $reason = 'Lintas periode (dimulai sebelum & masih berjalan)';
                    }

                    $partialCycles[] = [
                        'id'          => $c->id,
                        'name'        => "Siklus {$c->id}",
                        'type'        => 'partial',
                        'label'       => 'Siklus Separuh',
                        'detail'      => $reason,
                        'start_date'  => $c->start_date ? $c->start_date->translatedFormat('d M Y') : 'Belum Dimulai',
                        'end_date'    => $c->end_date ? $c->end_date->translatedFormat('d M Y') : 'Sekarang',
                        'phase'       => ucfirst($c->current_phase),
                    ];
                }
            }

            // Breakdown performa per fase dalam periode terpilih
            $phaseBreakdown = [];
            $phases = ['penetasan', 'pembesaran', 'prepupa'];

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
                'currentCycle'     => null,
                'totalFeed'        => $totalFeed,
                'finalMaggotWeight'=> $finalMaggotWeight,
                'initialMaggotWeight' => $initialMaggotWeight,
                'netMaggotGain'    => $netMaggotGain,
                'fcr'              => $fcr,
                'durationDays'     => $durationDays,
                'avgTemp'          => $avgTemp,
                'avgHumid'         => $avgHumid,
                'completedCycles'  => $completedCycles,
                'partialCycles'    => $partialCycles,
                'totalInvolvedCycles' => count($completedCycles) + count($partialCycles),
                'phaseBreakdown'   => $phaseBreakdown,
                'chartLabels'      => $chartData['labels'],
                'chartMaggot'      => $chartData['maggot'],
                'chartFeed'        => $chartData['feed'],
                'chartTemp'        => $chartData['temp'],
                'chartHumid'       => $chartData['humid'],
                'printLogs'        => $allLogs,
                'observationLogs'  => ObservationLog::with(['environmentLog', 'cycle'])
                    ->whereBetween('timestamp', [$start, $end])
                    ->orderBy('timestamp', 'desc')
                    ->orderBy('id', 'desc')
                    ->paginate(10),
            ];
        }

        // Mode Siklus
        $cycle = Cycle::find($this->selectedCycleId);

        $allLogs = ObservationLog::with('environmentLog')
            ->where('cycle_id', $this->selectedCycleId)
            ->orderBy('timestamp', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $envLogs = EnvironmentLog::where('cycle_id', $this->selectedCycleId)->get();

        $totalFeed = (float) $allLogs->sum('feed_weight');
        $finalMaggotWeight = (float) ($allLogs->last()?->maggot_weight ?? 0.0);
        $initialMaggotWeight = (float) ($allLogs->first()?->maggot_weight ?? 0.0);
        $netMaggotGain = max(0, $finalMaggotWeight - $initialMaggotWeight);

        $fcr = $finalMaggotWeight > 0 ? round($totalFeed / $finalMaggotWeight, 2) : 0.0;

        $durationDays = 0;
        if ($cycle && $cycle->start_date) {
            $endDate = $cycle->end_date ?? now();
            $durationDays = (int) Carbon::parse($cycle->start_date)->diffInDays(Carbon::parse($endDate)) + 1;
        }

        $avgTemp = $envLogs->count() > 0 ? round((float) $envLogs->avg('temperature'), 1) : 0.0;
        $avgHumid = $envLogs->count() > 0 ? round((float) $envLogs->avg('humidity'), 1) : 0.0;

        $phaseBreakdown = [];
        $phases = ['penetasan', 'pembesaran', 'prepupa'];

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
            'initialMaggotWeight' => $initialMaggotWeight,
            'netMaggotGain'    => $netMaggotGain,
            'fcr'              => $fcr,
            'durationDays'     => $durationDays,
            'avgTemp'          => $avgTemp,
            'avgHumid'         => $avgHumid,
            'completedCycles'  => [],
            'partialCycles'    => [],
            'totalInvolvedCycles' => 1,
            'phaseBreakdown'   => $phaseBreakdown,
            'chartLabels'      => $chartData['labels'],
            'chartMaggot'      => $chartData['maggot'],
            'chartFeed'        => $chartData['feed'],
            'chartTemp'        => $chartData['temp'],
            'chartHumid'       => $chartData['humid'],
            'printLogs'        => $allLogs,
            'observationLogs'  => ObservationLog::with(['environmentLog', 'cycle'])
                ->where('cycle_id', $this->selectedCycleId)
                ->orderBy('timestamp', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(10),
        ];
    }
};
?>

<div>
    <!-- Element DOM tersembunyi untuk mentransfer data array grafik yang selalu reaktif saat Livewire commit -->
    <div
        id="reportChartDataHolder"
        data-labels="{{ json_encode($chartLabels) }}"
        data-maggot="{{ json_encode($chartMaggot) }}"
        data-feed="{{ json_encode($chartFeed) }}"
        data-temp="{{ json_encode($chartTemp) }}"
        data-humid="{{ json_encode($chartHumid) }}"
        style="display: none;"
    ></div>

    <!-- 1. TAMPILAN INTERAKTIF LAYAR (Hanya Muncul di Layar Web, Otomatis Tersembunyi Saat Dicetak) -->
    <div class="no-print space-y-(--size-26) w-full">
        <!-- Header Halaman & Tombol Lonceng Notifikasi -->
        <div class="flex items-start sm:items-center justify-between flex-col sm:flex-row gap-3">
            <div>
                <h1 class="text-(--prime-colour) text-3xl sm:text-(length:--size-42) font-bold leading-tight">
                    Laporan Budidaya
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Rekapitulasi performa, konsumsi pakan, pertumbuhan bobot, dan evaluasi lingkungan.
                </p>
            </div>
            <livewire:notification-bell />
        </div>

        <!-- Mode Switch Tabs -->
        <div class="flex items-center w-full sm:w-auto">
            <div class="inline-flex h-[58px] p-1.5 bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] items-center gap-1.5 shadow-xs w-full sm:w-auto shrink-0">
                <button
                    type="button"
                    wire:click="setReportMode('periodic')"
                    class="flex-1 sm:flex-initial h-full px-5 rounded-xl font-semibold text-sm transition-all cursor-pointer flex items-center justify-center gap-2 {{ $reportMode === 'periodic' ? 'bg-(--prime-colour) text-(--fg-colour) shadow-xs' : 'text-(--text-colour) hover:bg-gray-100' }}"
                >
                    <x-lucide-calendar-range class="w-4 h-4"/>
                    <span>Laporan Periodik</span>
                </button>
                <button
                    type="button"
                    wire:click="setReportMode('cycle')"
                    class="flex-1 sm:flex-initial h-full px-5 rounded-xl font-semibold text-sm transition-all cursor-pointer flex items-center justify-center gap-2 {{ $reportMode === 'cycle' ? 'bg-(--prime-colour) text-(--fg-colour) shadow-xs' : 'text-(--text-colour) hover:bg-gray-100' }}"
                >
                    <x-lucide-refresh-cw class="w-4 h-4"/>
                    <span>Laporan Siklus</span>
                </button>
            </div>
        </div>

        <!-- Filter Toolbar & Tombol Aksi (Responsif di Mobile, Sejajar di Desktop) -->
        <div class="flex flex-col md:flex-row gap-3 justify-between w-full items-stretch md:items-center">
            @if($reportMode === 'periodic')
                <!-- Toolbar Mode Periodik: Preset & Tanggal -->
                <div class="flex flex-col md:flex-row items-stretch md:items-center gap-3 w-full md:w-auto">
                    <!-- Dropdown Pilihan Preset (Sama dengan Dropdown Siklus ke) -->
                    <div x-data="{ openDropdown: false }" class="inline-flex h-[58px] gap-(--size-10) items-center justify-between px-(--size-16) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs whitespace-nowrap w-full md:w-auto shrink-0">
                        <span>Preset:</span>
                        <div class="relative inline-block">
                            <button
                                @click="openDropdown = !openDropdown"
                                type="button"
                                class="rounded-(--size-16) inline-flex justify-between items-center gap-(--size-10) input-text text-(--size-16) hover:bg-(--bg2-colour) cursor-pointer whitespace-nowrap shrink-0"
                            >
                                <span>
                                    @if($periodicPreset === 'today') Hari Ini
                                    @elseif($periodicPreset === '7days') 7 Hari Terakhir
                                    @elseif($periodicPreset === '30days') 30 Hari Terakhir
                                    @elseif($periodicPreset === 'this_month') Bulan Ini
                                    @else Kustom
                                    @endif
                                </span>
                                <x-lucide-chevron-down class="w-(--size-16)"/>
                            </button>

                            <div
                                x-show="openDropdown"
                                @click.outside="openDropdown = false"
                                x-transition.opacity.duration.200ms
                                class="absolute left-0 top-full mt-(--size-10) w-52 bg-white border border-gray-300 rounded-(--size-16) shadow-xl z-50 overflow-hidden"
                                x-cloak
                            >
                                <button
                                    type="button"
                                    wire:click="setPeriodicPreset('today')"
                                    @click="openDropdown = false"
                                    class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 border-b border-gray-100 cursor-pointer {{ $periodicPreset === 'today' ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
                                >
                                    <span>Hari Ini</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="setPeriodicPreset('7days')"
                                    @click="openDropdown = false"
                                    class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 border-b border-gray-100 cursor-pointer {{ $periodicPreset === '7days' ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
                                >
                                    <span>7 Hari Terakhir</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="setPeriodicPreset('30days')"
                                    @click="openDropdown = false"
                                    class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 border-b border-gray-100 cursor-pointer {{ $periodicPreset === '30days' ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
                                >
                                    <span>30 Hari Terakhir</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="setPeriodicPreset('this_month')"
                                    @click="openDropdown = false"
                                    class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 border-b border-gray-100 cursor-pointer {{ $periodicPreset === 'this_month' ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
                                >
                                    <span>Bulan Ini</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="setPeriodicPreset('custom')"
                                    @click="openDropdown = false"
                                    class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 cursor-pointer {{ $periodicPreset === 'custom' ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
                                >
                                    <span>Kustom Tanggal</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Input Rentang Tanggal Kalender Kustom (Dari & Sampai - Responsif Bebas Overflow) -->
                    <div class="flex flex-col sm:flex-row md:inline-flex md:h-[58px] gap-2.5 md:gap-(--size-10) items-stretch sm:items-center px-3 sm:px-4 md:px-(--size-16) py-2.5 md:py-0 bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs text-sm md:text-(length:--size-16) w-full md:w-auto shrink-0">
                        <div class="flex items-center justify-between sm:justify-start gap-2 w-full md:w-auto">
                            <span class="font-medium text-(--text-colour) shrink-0">Dari:</span>
                            <x-custom-date-picker wire:model.live="startDate" />
                        </div>
                        <span class="text-gray-300 font-bold hidden sm:inline md:inline">&mdash;</span>
                        <div class="flex items-center justify-between sm:justify-start gap-2 w-full md:w-auto">
                            <span class="font-medium text-(--text-colour) shrink-0">Sampai:</span>
                            <x-custom-date-picker wire:model.live="endDate" />
                        </div>
                        <div class="flex items-center justify-center pt-1 sm:pt-0">
                            <span class="text-xs text-emerald-800 bg-emerald-100 px-2.5 py-0.5 rounded-full font-bold whitespace-nowrap">
                                {{ $durationDays }} Hari
                            </span>
                        </div>
                    </div>
                </div>
            @else
                <!-- Toolbar Mode Siklus: Siklus ke & Status (Vertikal di Mobile, Horisontal di Desktop) -->
                <div class="flex flex-col md:flex-row items-stretch md:items-center gap-3 w-full md:w-auto">
                    <!-- Dropdown Pilihan Siklus -->
                    <div x-data="{ openDropdown: false }" class="inline-flex h-[58px] gap-(--size-10) items-center justify-between px-4 md:px-(--size-16) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs whitespace-nowrap w-full md:w-auto shrink-0">
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
                                class="absolute left-0 top-full mt-(--size-10) w-(--size-492) max-w-[calc(100vw-3rem)] bg-white border border-gray-300 rounded-(--size-16) shadow-xl z-50 max-h-72 overflow-y-auto"
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
                                            {{ $item->start_date ? $item->start_date->translatedFormat('d M Y') : 'Belum Dimulai' }} &mdash; {{ $item->end_date ? $item->end_date->translatedFormat('d M Y') : 'Sekarang' }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Status Siklus Pill -->
                    <div class="inline-flex h-[58px] gap-(--size-10) items-center justify-between px-4 md:px-(--size-16) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs text-sm md:text-(length:--size-16) w-full md:w-auto shrink-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-gray-500 font-medium">Status:</span>
                            <span class="font-bold text-(--prime-colour)">
                                {{ $currentCycle?->is_active ? 'Aktif (' . ucfirst($currentCycle->current_phase) . ')' : 'Selesai / Panen' }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-400 font-semibold shrink-0 ml-2">
                            ({{ $currentCycle?->start_date ? $durationDays . ' Hari' : 'Belum Dimulai' }})
                        </span>
                    </div>
                </div>
            @endif

            <!-- Tombol Ekspor CSV & Cetak Laporan -->
            <div class="grid grid-cols-2 md:flex md:flex-row items-center gap-2.5 w-full md:w-auto shrink-0">
                <button
                    wire:click="exportCsv"
                    type="button"
                    class="h-[58px] w-full md:w-auto gap-(--size-10) px-4 md:px-(--size-26) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) font-medium text-sm md:text-(length:--size-16) cursor-pointer hover:opacity-90 flex items-center justify-center whitespace-nowrap shadow-xs"
                >
                    <x-lucide-download class="w-5 md:w-(--size-26)"/>
                    <span>Ekspor CSV</span>
                </button>

                <button
                    onclick="window.printReport ? window.printReport() : window.print()"
                    type="button"
                    class="h-[58px] w-full md:w-auto gap-(--size-10) px-4 md:px-(--size-26) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) font-medium text-sm md:text-(length:--size-16) cursor-pointer hover:opacity-90 flex items-center justify-center whitespace-nowrap shadow-xs"
                >
                    <x-lucide-printer class="w-5 md:w-(--size-26)"/>
                    <span>Cetak Laporan</span>
                </button>
            </div>
        </div>

        <!-- 3 Kartu Ringkasan KPI Utama (Vertikal 1 Kolom Penuh di Mobile, Horisontal 3 Kolom di Desktop) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-(--size-26) w-full">
            <!-- 1. Total Pakan Kumulatif / Periode -->
            <div class="flex flex-col justify-between gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
                <div class="flex flex-row items-center gap-(--size-16)">
                    <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                        <x-lucide-apple class="w-(--size-26) h-(--size-26)"/>
                    </div>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                        {{ $reportMode === 'periodic' ? 'Total Pakan Periode' : 'Total Pakan Kumulatif' }}
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
                        {{ $reportMode === 'periodic' ? 'Akumulasi pakan pada rentang tanggal terpilih' : 'Akumulasi konsumsi pakan siklus' }}
                    </p>
                </div>
            </div>

            <!-- 2. Hasil Akhir / Bobot Maggot -->
            <div class="flex flex-col justify-between gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
                <div class="flex flex-row items-center gap-(--size-16)">
                    <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                        <x-lucide-weight class="w-(--size-26) h-(--size-26)"/>
                    </div>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                        {{ $reportMode === 'periodic' ? 'Bobot Maggot Akhir' : 'Hasil Akhir Maggot' }}
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
                        @if($netMaggotGain > 0)
                            Pertambahan bersih: <span class="font-semibold text-emerald-600">+{{ number_format($netMaggotGain, 1) }} kg</span>
                        @else
                            Biomassa maggot tercatat
                        @endif
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

        <!-- 2 Grafik Analitik (Responsif 1 Kolom di Mobile, 2 Kolom di Desktop) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-(--size-26) w-full items-stretch">
            <!-- Grafik 1: Pertumbuhan Bobot Maggot vs Konsumsi Pakan -->
            <div class="col-span-1 flex flex-col justify-between gap-(--size-16) p-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
                <div>
                    <div class="flex flex-row items-center justify-between flex-wrap gap-2">
                        <div class="flex flex-row items-center gap-(--size-16)">
                            <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                                <x-lucide-trending-up class="w-(--size-26) h-(--size-26)"/>
                            </div>
                            <div>
                                <h3 class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                                    Pertumbuhan & Pakan
                                </h3>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Trajektori biomassa maggot dan pemberian pakan
                                </p>
                            </div>
                        </div>

                        <!-- Legend Indikator -->
                        <div class="flex items-center gap-3 text-xs whitespace-nowrap shrink-0">
                            <span class="flex items-center gap-1.5 text-gray-700 font-semibold">
                                <span class="w-3 h-3 rounded-sm bg-[#163428]"></span> Bobot Maggot (kg)
                            </span>
                            <span class="flex items-center gap-1.5 text-amber-700 font-semibold">
                                <span class="w-3 h-3 rounded-sm bg-amber-500"></span> Pakan (kg)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Canvas Grafik Pertumbuhan -->
                <div wire:ignore class="relative w-full h-[300px] border border-gray-100 rounded-xl p-3 bg-gray-50/50">
                    <canvas id="growthReportChartCanvas" style="width: 100%; height: 100%;"></canvas>
                </div>
            </div>

            <!-- Grafik 2: Tren Kondisi Lingkungan (Suhu & Kelembapan yang Diharmonisasikan) -->
            <div class="col-span-1 flex flex-col justify-between gap-(--size-16) p-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
                <div>
                    <div class="flex flex-row items-center justify-between flex-wrap gap-2">
                        <div class="flex flex-row items-center gap-(--size-16)">
                            <div class="p-(--size-10) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) shrink-0 flex items-center justify-center">
                                <x-lucide-activity class="w-(--size-26) h-(--size-26)"/>
                            </div>
                            <div>
                                <h3 class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                                    Tren Lingkungan
                                </h3>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Kondisi suhu (&deg;C) dan kelembapan (%) saat observasi
                                </p>
                            </div>
                        </div>

                        <!-- Legend Indikator Elegan dan Lembut -->
                        <div class="flex items-center gap-3 text-xs whitespace-nowrap shrink-0">
                            <span class="flex items-center gap-1.5 text-[#163428] font-semibold">
                                <span class="w-3.5 h-0.5 bg-[#163428] rounded"></span> Suhu (&deg;C)
                            </span>
                            <span class="flex items-center gap-1.5 text-sky-700 font-semibold">
                                <span class="w-3.5 h-0.5 bg-sky-600 border-b border-dashed border-sky-600"></span> Kelembapan (%)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Canvas Grafik Lingkungan -->
                <div wire:ignore class="relative w-full h-[300px] border border-gray-100 rounded-xl p-3 bg-gray-50/50">
                    <canvas id="envReportChartCanvas" style="width: 100%; height: 100%;"></canvas>
                </div>
            </div>
        </div>

        <!-- Rekapitulasi Siklus dalam Periode (Khusus Mode Periodik: Fixed wxh = 3x2, Overflow > 6 Scrollable ke Bawah) -->
        @if($reportMode === 'periodic')
            <div class="flex flex-col gap-(--size-16) px-(--size-26) py-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex flex-row gap-(--size-16) items-center">
                        <x-lucide-refresh-cw class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
                        <div>
                            <h2 class="text-(--prime-colour) text-lg sm:text-(length:--size-26) font-bold leading-tight">
                                Rekapitulasi Siklus dalam Periode (Total {{ $totalInvolvedCycles }} Siklus)
                            </h2>
                            <p class="text-xs text-gray-400">
                                Klasifikasi siklus yang berjalan penuh vs siklus separuh/sebagian pada rentang tanggal ini
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
                        <span class="px-3.5 py-1.5 bg-emerald-100 text-emerald-900 border border-emerald-300 rounded-xl text-xs font-bold flex items-center gap-1.5">
                            <x-lucide-check-circle-2 class="w-4 h-4 text-emerald-700"/>
                            {{ count($completedCycles) }} Siklus Selesai Penuh
                        </span>
                        <span class="px-3.5 py-1.5 bg-amber-100 text-amber-900 border border-amber-300 rounded-xl text-xs font-bold flex items-center gap-1.5">
                            <x-lucide-clock class="w-4 h-4 text-amber-700"/>
                            {{ count($partialCycles) }} Siklus Separuh
                        </span>
                    </div>
                </div>

                <!-- Fixed Grid (1 Kolom Vertikal di Mobile, 3 Kolom di Desktop, Overflow Scroll Vertikal) -->
                <div class="max-h-[380px] md:max-h-[285px] overflow-y-auto pr-1">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @forelse(array_merge($completedCycles, $partialCycles) as $item)
                            <div class="p-4 rounded-xl border-[1.5px] {{ $item['type'] === 'completed' ? 'border-emerald-300 bg-emerald-50/40' : 'border-amber-300 bg-amber-50/40' }} flex flex-col justify-between gap-3 shadow-2xs">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full {{ $item['type'] === 'completed' ? 'bg-emerald-600' : 'bg-amber-500' }}"></span>
                                        <span class="font-bold text-base text-[#163428]">{{ $item['name'] }}</span>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $item['type'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $item['label'] }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 space-y-1.5 border-t border-gray-200/70 pt-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-500">Status Fase:</span>
                                        <span class="font-semibold text-gray-800">{{ $item['phase'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-500">Rentang Siklus:</span>
                                        <span class="font-medium text-gray-700">{{ $item['start_date'] }} &mdash; {{ $item['end_date'] }}</span>
                                    </div>
                                    <div class="text-[11px] text-gray-500 italic pt-1 truncate">
                                        &bull; {{ $item['detail'] }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-3 py-8 text-center text-sm text-gray-400 bg-(--bg-colour) rounded-xl border border-dashed border-gray-300">
                                Tidak ada aktivitas siklus yang teridentifikasi dalam rentang waktu tanggal ini.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <!-- Tabel 1: Analisis Performa Per Fase Budidaya -->
        <div class="flex flex-col gap-(--size-16) px-(--size-26) py-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex flex-row gap-(--size-16) items-center">
                <x-lucide-bar-chart-3 class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
                <div>
                    <h2 class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                        {{ $reportMode === 'periodic' ? 'Analisis Performa Fase dalam Periode Terpilih' : 'Analisis Performa Per Fase Budidaya' }}
                    </h2>
                    <p class="text-xs text-gray-400">
                        {{ $reportMode === 'periodic' ? 'Ringkasan observasi dan kondisi lingkungan per fase pada rentang waktu ini' : 'Ringkasan komparasi performa antar fase budidaya pada siklus ini' }}
                    </p>
                </div>
            </div>

            <!-- 1. Tampilan Card Khusus Mobile (Analisis Fase) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:hidden mt-1">
                @foreach($phaseBreakdown as $key => $p)
                    <div class="p-4 bg-(--bg-colour) border border-(--outline-colour) rounded-xl flex flex-col gap-2.5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                            <div class="flex items-center gap-2 font-bold text-sm text-(--prime-colour)">
                                @if($key === 'penetasan')
                                    <x-lucide-egg class="w-4 h-4 text-(--prime-colour)" />
                                @elseif($key === 'pembesaran')
                                    <x-lucide-worm class="w-4 h-4 text-(--prime-colour)" />
                                @else
                                    <x-lucide-bug class="w-4 h-4 text-(--prime-colour)" />
                                @endif
                                <span>{{ $p['name'] }}</span>
                            </div>
                            <span class="text-xs text-gray-500 font-semibold">{{ $p['log_count'] }} kali log</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-400 text-[11px] block">Total Pakan</span>
                                <span class="font-bold text-gray-800">{{ number_format($p['total_feed'], 1) }} kg</span>
                            </div>
                            <div>
                                <span class="text-gray-400 text-[11px] block">Bobot Akhir</span>
                                <span class="font-bold text-emerald-800">{{ number_format($p['end_maggot'], 1) }} kg</span>
                            </div>
                            <div>
                                <span class="text-gray-400 text-[11px] block">Suhu Rata-rata</span>
                                <span class="font-semibold text-gray-800">{{ $p['avg_temp'] !== '-' ? $p['avg_temp'] . '°C' : '-' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 text-[11px] block">Kelembapan</span>
                                <span class="font-semibold text-gray-800">{{ $p['avg_humid'] !== '-' ? $p['avg_humid'] . '%' : '-' }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- 2. Tampilan Tabel Khusus Desktop (Analisis Fase) -->
            <div class="hidden md:block overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) w-full shadow-xs mt-2">
                <table class="w-full text-left border-collapse">
                    <thead class="border-b-[1.5px] border-(--prime-light-colour) bg-(--prime-colour)">
                        <tr>
                            <th class="min-w-[160px]">Fase Budidaya</th>
                            <th class="min-w-[130px]">Jumlah Log</th>
                            <th class="min-w-[130px]">Total Pakan</th>
                            <th class="min-w-[160px]">Bobot Maggot Fase</th>
                            <th class="min-w-[160px]">Suhu Aktual (Ideal)</th>
                            <th class="border-r-0 min-w-[160px]">Kelembapan Aktual (Ideal)</th>
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

        <!-- Tabel 2: Rincian Log Catatan Observasi Interaktif (Berhalaman / Paginated) -->
        <div class="flex flex-col gap-(--size-16) px-(--size-26) py-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex flex-row gap-(--size-16) items-center">
                <x-lucide-table class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
                <div>
                    <h2 class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                        {{ $reportMode === 'periodic' ? 'Rincian Log Observasi Periode' : 'Rincian Log Harian Siklus' }}
                    </h2>
                    <p class="text-xs text-gray-400">
                        {{ $reportMode === 'periodic' ? 'Daftar catatan harian yang terekam pada rentang tanggal ' . Carbon::parse($startDate)->translatedFormat('d M Y') . ' s/d ' . Carbon::parse($endDate)->translatedFormat('d M Y') : 'Daftar catatan harian yang terekam pada siklus terpilih' }}
                    </p>
                </div>
            </div>

            <!-- 1. Tampilan Card Khusus Mobile (Log Observasi) -->
            <div class="space-y-3 md:hidden mt-1">
                @forelse($observationLogs as $item)
                    <div wire:key="rep-obs-mobile-card-{{ $item->id }}" class="p-4 bg-(--bg-colour) border border-(--outline-colour) rounded-xl flex flex-col gap-2.5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                            <div class="flex items-center gap-2 text-xs font-bold text-(--prime-colour)">
                                <x-lucide-calendar class="w-3.5 h-3.5"/>
                                <span>{{ $item->timestamp ? $item->timestamp->translatedFormat('d M Y') : '-' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                @if($reportMode === 'periodic')
                                    <span class="px-2 py-0.5 bg-emerald-50 text-[#163428] font-bold rounded text-[11px] border border-emerald-200">
                                        Siklus {{ $item->cycle_id ?? '-' }}
                                    </span>
                                @endif
                                <span class="px-2.5 py-0.5 bg-gray-100 text-gray-700 font-bold rounded text-[11px] capitalize">
                                    {{ $item->phase_name }}
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-400 text-[11px] block">Suhu & Kelembapan</span>
                                <span class="font-bold text-gray-800">{{ $item->environmentLog->temperature ?? '-' }}&deg;C &bull; {{ $item->environmentLog->humidity ?? '-' }}%</span>
                            </div>
                            <div>
                                <span class="text-gray-400 text-[11px] block">Pakan / Bobot</span>
                                <span class="font-bold text-gray-800">{{ $item->feed_weight }} kg / {{ $item->maggot_weight }} kg</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-gray-400 bg-(--bg-colour) rounded-xl border border-(--outline-colour)">
                        Tidak ada catatan observasi untuk {{ $reportMode === 'periodic' ? 'rentang periode tanggal ini' : 'siklus ini' }}.
                    </div>
                @endforelse
            </div>

            <!-- 2. Tampilan Tabel Khusus Desktop (Log Observasi) -->
            <div class="hidden md:block overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) w-full shadow-xs mt-2">
                <table class="w-full text-left border-collapse">
                    <thead class="border-b-[1.5px] border-(--prime-light-colour) bg-(--prime-colour)">
                        <tr>
                            <th class="min-w-[180px]">Tanggal</th>
                            @if($reportMode === 'periodic')
                                <th class="min-w-[110px]">Siklus</th>
                            @endif
                            <th class="min-w-[130px]">Fase</th>
                            <th class="min-w-[100px]">Suhu</th>
                            <th class="min-w-[120px]">Kelembapan</th>
                            <th class="min-w-[140px]">Pakan Diberikan</th>
                            <th class="border-r-0 min-w-[140px]">Berat Maggot</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($observationLogs as $item)
                            <tr wire:key="rep-obs-desktop-row-{{ $item->id }}" class="border-b-[1.5px] border-(--outline-colour) hover:bg-gray-50 transition-colors">
                                <td>{{ $item->timestamp ? $item->timestamp->translatedFormat('d F Y') : '-' }}</td>
                                @if($reportMode === 'periodic')
                                    <td>
                                        <span class="px-2 py-0.5 bg-emerald-50 text-[#163428] font-bold rounded-md text-xs border border-emerald-200">
                                            Siklus {{ $item->cycle_id ?? '-' }}
                                        </span>
                                    </td>
                                @endif
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
                                <td colspan="{{ $reportMode === 'periodic' ? 7 : 6 }}" class="border-r-0 py-8 text-center text-gray-400">
                                    Tidak ada catatan observasi untuk {{ $reportMode === 'periodic' ? 'rentang periode tanggal ini' : 'siklus ini' }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($observationLogs->hasPages())
                <div class="pt-2">
                    {{ $observationLogs->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- 2. DEDICATED PRINTABLE DOCUMENT LAYOUT (Hanya Tampil Saat Cetak / window.print) -->
    <div class="print-only font-sans text-black bg-white w-full">
        <!-- Header Kop Dokumen Resmi -->
        <div class="border-b-2 border-black pb-3 mb-4">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-lg font-black uppercase tracking-wider text-black">
                        SISTEM MONITORING BUDIDAYA MAGGOT BSF
                    </h1>
                    <h2 class="text-sm font-bold text-gray-800 mt-0.5">
                        {{ $reportMode === 'periodic' ? 'LAPORAN PERIODIK REKAPITULASI HASIL BUDIDAYA & LINGKUNGAN' : 'LAPORAN REKAPITULASI BUDIDAYA - SIKLUS ' . ($currentCycle?->id ?? '-') }}
                    </h2>
                    <p class="text-[11px] text-gray-600 mt-0.5">
                        Dokumen resmi rekapitulasi data biomassa maggot, efisiensi pakan, dan parameter lingkungan kandang.
                    </p>
                </div>
                <div class="text-right text-[11px] text-gray-600 space-y-0.5">
                    <div><strong>Waktu Cetak:</strong> {{ now()->translatedFormat('d F Y, H:i') }} WIB</div>
                    <div><strong>Operator:</strong> {{ auth()->user()->name ?? 'Administrator' }}</div>
                </div>
            </div>

            <!-- Metadata Periode / Siklus -->
            <div class="mt-3 pt-2 border-t border-gray-300 text-[11px] flex justify-between items-center text-gray-800">
                <div>
                    @if($reportMode === 'periodic')
                        <strong>Rentang Waktu:</strong> {{ Carbon::parse($startDate)->translatedFormat('d M Y') }} &mdash; {{ Carbon::parse($endDate)->translatedFormat('d M Y') }} ({{ $durationDays }} Hari)
                    @else
                        <strong>Rentang Siklus:</strong> {{ $currentCycle?->start_date?->translatedFormat('d M Y') ?? 'Belum Dimulai' }} &mdash; {{ $currentCycle?->end_date?->translatedFormat('d M Y') ?? ($currentCycle?->is_active ? 'Sedang Berjalan' : '-') }} ({{ $durationDays }} Hari)
                    @endif
                </div>
                <div>
                    @if($reportMode === 'periodic')
                        <strong>Siklus Terlibat:</strong> {{ $totalInvolvedCycles }} Siklus ({{ count($completedCycles) }} Selesai, {{ count($partialCycles) }} Separuh)
                    @else
                        <strong>Status Siklus:</strong> {{ $currentCycle?->is_active ? 'Aktif (' . ucfirst($currentCycle->current_phase) . ')' : 'Selesai / Panen' }}
                    @endif
                </div>
            </div>
        </div>

        <!-- 1. Ringkasan Parameter & Performa Utama -->
        <div class="mb-5">
            <h3 class="text-xs font-bold uppercase tracking-wider text-black mb-1.5 pb-0.5 border-b border-gray-400">
                1. Ringkasan Parameter & Performa Utama
            </h3>
            <table class="w-full text-xs border border-gray-300 border-collapse">
                <tbody>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <td class="p-2 font-bold text-left w-1/4 border-r border-gray-200">Total Konsumsi Pakan:</td>
                        <td class="p-2 text-left w-1/4 border-r border-gray-200 font-semibold">{{ number_format($totalFeed, 2) }} kg</td>
                        <td class="p-2 font-bold text-left w-1/4 border-r border-gray-200">Rata-rata Suhu:</td>
                        <td class="p-2 text-left w-1/4 font-semibold">{{ $avgTemp }}°C</td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <td class="p-2 font-bold text-left border-r border-gray-200">Bobot Maggot Akhir:</td>
                        <td class="p-2 text-left border-r border-gray-200 font-semibold">{{ number_format($finalMaggotWeight, 2) }} kg</td>
                        <td class="p-2 font-bold text-left border-r border-gray-200">Rata-rata Kelembapan:</td>
                        <td class="p-2 text-left font-semibold">{{ $avgHumid }}%</td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td class="p-2 font-bold text-left border-r border-gray-200">Pertambahan Bobot Bersih:</td>
                        <td class="p-2 text-left border-r border-gray-200 font-semibold">+{{ number_format($netMaggotGain, 2) }} kg</td>
                        <td class="p-2 font-bold text-left border-r border-gray-200">Konversi Pakan (FCR):</td>
                        <td class="p-2 text-left font-semibold">{{ $fcr > 0 ? number_format($fcr, 2) . ' per kg maggot' : '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 2. Visualisasi Grafik Tren Pertumbuhan & Kondisi Lingkungan (Tampil Bersih di Cetak Dokumen) -->
        <div class="mb-5 break-inside-avoid">
            <h3 class="text-xs font-bold uppercase tracking-wider text-black mb-2 pb-0.5 border-b border-gray-400">
                2. Visualisasi Grafik Tren Pertumbuhan & Kondisi Lingkungan
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="border border-gray-300 p-2.5 rounded bg-gray-50/20 text-center">
                    <div class="text-[11px] font-bold text-black mb-1.5">
                        Pertumbuhan Bobot Maggot vs Konsumsi Pakan (kg)
                    </div>
                    <img id="printGrowthChartImg" class="w-full h-[170px] object-contain mx-auto" alt="Grafik Pertumbuhan Bobot & Pakan">
                </div>
                <div class="border border-gray-300 p-2.5 rounded bg-gray-50/20 text-center">
                    <div class="text-[11px] font-bold text-black mb-1.5">
                        Fluktuasi Suhu (&deg;C) & Kelembapan (%)
                    </div>
                    <img id="printEnvChartImg" class="w-full h-[170px] object-contain mx-auto" alt="Grafik Tren Lingkungan">
                </div>
            </div>
        </div>

        <!-- 3. Analisis Performa Per Fase Budidaya -->
        <div class="mb-5">
            <h3 class="text-xs font-bold uppercase tracking-wider text-black mb-1.5 pb-0.5 border-b border-gray-400">
                3. Analisis Performa Per Fase Budidaya
            </h3>
            <table class="w-full text-xs border border-gray-300 text-left border-collapse">
                <thead class="bg-gray-100 font-bold border-b border-gray-300">
                    <tr>
                        <th class="p-2 border-r border-gray-300 text-black text-left">Fase Budidaya</th>
                        <th class="p-2 border-r border-gray-300 text-black text-center">Frekuensi Log</th>
                        <th class="p-2 border-r border-gray-300 text-black text-right">Total Pakan (kg)</th>
                        <th class="p-2 border-r border-gray-300 text-black text-right">Bobot Maggot (kg)</th>
                        <th class="p-2 border-r border-gray-300 text-black text-center">Suhu Aktual (Ideal)</th>
                        <th class="p-2 text-black text-center">Kelembapan Aktual (Ideal)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($phaseBreakdown as $key => $p)
                        <tr class="border-b border-gray-200">
                            <td class="p-2 font-bold border-r border-gray-200">{{ $p['name'] }}</td>
                            <td class="p-2 border-r border-gray-200 text-center">{{ $p['log_count'] }} kali</td>
                            <td class="p-2 border-r border-gray-200 text-right">{{ number_format($p['total_feed'], 2) }}</td>
                            <td class="p-2 border-r border-gray-200 text-right font-semibold">
                                {{ number_format($p['end_maggot'], 2) }}
                                @if($p['growth_gain'] > 0)
                                    <span class="text-[10px] text-gray-500 block">(+{{ number_format($p['growth_gain'], 2) }})</span>
                                @endif
                            </td>
                            <td class="p-2 border-r border-gray-200 text-center">
                                {{ $p['avg_temp'] !== '-' ? $p['avg_temp'] . '°C' : '-' }}
                                <span class="text-[10px] text-gray-500 block">({{ $p['ideal_temp'] }})</span>
                            </td>
                            <td class="p-2 text-center">
                                {{ $p['avg_humid'] !== '-' ? $p['avg_humid'] . '%' : '-' }}
                                <span class="text-[10px] text-gray-500 block">({{ $p['ideal_humid'] }})</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- 4. Rincian Siklus yang Terlibat (Khusus Mode Periodik) -->
        @if($reportMode === 'periodic' && count(array_merge($completedCycles, $partialCycles)) > 0)
            <div class="mb-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-black mb-1.5 pb-0.5 border-b border-gray-400">
                    4. Rincian Status Siklus dalam Periode
                </h3>
                <table class="w-full text-xs border border-gray-300 text-left border-collapse">
                    <thead class="bg-gray-100 font-bold border-b border-gray-300">
                        <tr>
                            <th class="p-2 border-r border-gray-300 text-black text-left">Nama Siklus</th>
                            <th class="p-2 border-r border-gray-300 text-black text-left">Klasifikasi</th>
                            <th class="p-2 border-r border-gray-300 text-black text-left">Fase Terakhir</th>
                            <th class="p-2 border-r border-gray-300 text-black text-left">Rentang Tanggal</th>
                            <th class="p-2 text-black text-left">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_merge($completedCycles, $partialCycles) as $cItem)
                            <tr class="border-b border-gray-200">
                                <td class="p-2 font-bold border-r border-gray-200">{{ $cItem['name'] }}</td>
                                <td class="p-2 border-r border-gray-200 font-semibold">
                                    {{ $cItem['label'] }}
                                </td>
                                <td class="p-2 border-r border-gray-200">{{ $cItem['phase'] }}</td>
                                <td class="p-2 border-r border-gray-200">{{ $cItem['start_date'] }} s/d {{ $cItem['end_date'] }}</td>
                                <td class="p-2 text-gray-600">{{ $cItem['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- 5. Rincian Seluruh Catatan Log Observasi -->
        <div class="mb-6">
            <h3 class="text-xs font-bold uppercase tracking-wider text-black mb-1.5 pb-0.5 border-b border-gray-400">
                {{ $reportMode === 'periodic' ? '5. Daftar Lengkap Log Catatan Observasi' : '4. Daftar Lengkap Log Catatan Observasi' }}
            </h3>
            <table class="w-full text-[11px] border border-gray-300 text-left border-collapse">
                <thead class="bg-gray-100 font-bold border-b border-gray-300">
                    <tr>
                        <th class="p-1.5 border-r border-gray-300 text-black text-center w-8">No</th>
                        <th class="p-1.5 border-r border-gray-300 text-black text-left">Tanggal</th>
                        @if($reportMode === 'periodic')
                            <th class="p-1.5 border-r border-gray-300 text-black text-left">Siklus</th>
                        @endif
                        <th class="p-1.5 border-r border-gray-300 text-black text-left">Fase</th>
                        <th class="p-1.5 border-r border-gray-300 text-black text-center">Suhu</th>
                        <th class="p-1.5 border-r border-gray-300 text-black text-center">Kelembapan</th>
                        <th class="p-1.5 border-r border-gray-300 text-black text-right">Pakan (kg)</th>
                        <th class="p-1.5 text-black text-right">Bobot Maggot (kg)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($printLogs as $idx => $log)
                        <tr class="border-b border-gray-200">
                            <td class="p-1.5 border-r border-gray-200 text-center">{{ $idx + 1 }}</td>
                            <td class="p-1.5 border-r border-gray-200 font-medium whitespace-nowrap">{{ $log->timestamp ? $log->timestamp->format('d/m/Y') : '-' }}</td>
                            @if($reportMode === 'periodic')
                                <td class="p-1.5 border-r border-gray-200 whitespace-nowrap">Siklus {{ $log->cycle_id ?? '-' }}</td>
                            @endif
                            <td class="p-1.5 border-r border-gray-200 capitalize whitespace-nowrap">{{ $log->phase_name }}</td>
                            <td class="p-1.5 border-r border-gray-200 text-center whitespace-nowrap">{{ $log->environmentLog->temperature ?? '-' }}°C</td>
                            <td class="p-1.5 border-r border-gray-200 text-center whitespace-nowrap">{{ $log->environmentLog->humidity ?? '-' }}%</td>
                            <td class="p-1.5 border-r border-gray-200 text-right whitespace-nowrap">{{ number_format((float)$log->feed_weight, 2) }}</td>
                            <td class="p-1.5 text-right font-semibold whitespace-nowrap">{{ number_format((float)$log->maggot_weight, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $reportMode === 'periodic' ? 8 : 7 }}" class="p-4 text-center text-gray-400">
                                Tidak ada data catatan observasi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@script
<script>
    let growthReportChart = null;
    let envReportChart = null;

    function initOrUpdateGrowthChart(labels, maggot, feed) {
        const canvas = document.getElementById('growthReportChartCanvas');
        if (!canvas || typeof Chart === 'undefined') return;

        const safeLabels = (labels && labels.length) ? labels : ['Belum ada data'];
        const safeMaggot = (maggot && maggot.length) ? maggot : [0];
        const safeFeed   = (feed && feed.length) ? feed : [0];

        if (growthReportChart) {
            growthReportChart.data.labels = safeLabels;
            growthReportChart.data.datasets[0].data = safeMaggot;
            growthReportChart.data.datasets[1].data = safeFeed;
            growthReportChart.update('none');
            return;
        }

        const existing = Chart.getChart(canvas);
        if (existing) {
            existing.destroy();
        }

        growthReportChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: safeLabels,
                datasets: [
                    {
                        label: 'Bobot Maggot (kg)',
                        data: safeMaggot,
                        borderColor: '#163428',
                        backgroundColor: 'rgba(22, 52, 40, 0.08)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#163428',
                        pointRadius: safeLabels.length > 20 ? 2 : 4,
                    },
                    {
                        label: 'Pakan (kg)',
                        data: safeFeed,
                        borderColor: '#F59E0B',
                        backgroundColor: 'transparent',
                        fill: false,
                        borderDash: [4, 4],
                        tension: 0.3,
                        borderWidth: 2,
                        pointBackgroundColor: '#F59E0B',
                        pointRadius: safeLabels.length > 20 ? 2 : 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#163428',
                        padding: 10,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#E5E7EB' },
                        ticks: { font: { size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, maxRotation: 45 }
                    }
                }
            }
        });
    }

    function initOrUpdateEnvChart(labels, temp, humid) {
        const canvas = document.getElementById('envReportChartCanvas');
        if (!canvas || typeof Chart === 'undefined') return;

        const safeLabels = (labels && labels.length) ? labels : ['Belum ada data'];
        const safeTemp   = (temp && temp.length) ? temp : [0];
        const safeHumid  = (humid && humid.length) ? humid : [0];

        if (envReportChart) {
            envReportChart.data.labels = safeLabels;
            envReportChart.data.datasets[0].data = safeTemp;
            envReportChart.data.datasets[1].data = safeHumid;
            envReportChart.update('none');
            return;
        }

        const existing = Chart.getChart(canvas);
        if (existing) {
            existing.destroy();
        }

        envReportChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: safeLabels,
                datasets: [
                    {
                        label: 'Suhu (°C)',
                        data: safeTemp,
                        borderColor: '#163428',
                        backgroundColor: 'transparent',
                        yAxisID: 'yTemp',
                        tension: 0.3,
                        borderWidth: 2.2,
                        pointBackgroundColor: '#163428',
                        pointRadius: safeLabels.length > 20 ? 1.5 : 3.5,
                        fill: false,
                    },
                    {
                        label: 'Kelembapan (%)',
                        data: safeHumid,
                        borderColor: '#0284C7',
                        backgroundColor: 'transparent',
                        yAxisID: 'yHumid',
                        borderDash: [5, 4],
                        tension: 0.3,
                        borderWidth: 2,
                        pointBackgroundColor: '#0284C7',
                        pointRadius: safeLabels.length > 20 ? 1.5 : 3.5,
                        fill: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#163428',
                        padding: 10,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.yAxisID === 'yTemp') {
                                    return ' Suhu: ' + (context.parsed.y !== null ? context.parsed.y.toFixed(1) + '°C' : '-');
                                }
                                return ' Kelembapan: ' + (context.parsed.y !== null ? context.parsed.y.toFixed(1) + '%' : '-');
                            }
                        }
                    }
                },
                scales: {
                    yTemp: {
                        type: 'linear',
                        position: 'left',
                        min: 0,
                        max: 50,
                        title: { display: true, text: 'Suhu (°C)', color: '#163428', font: { size: 11, weight: '600' } },
                        grid: { color: '#F3F4F6' },
                        ticks: {
                            stepSize: 10,
                            font: { size: 11 },
                            callback: (v) => v + '°C'
                        }
                    },
                    yHumid: {
                        type: 'linear',
                        position: 'right',
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'Kelembapan (%)', color: '#0284C7', font: { size: 11, weight: '600' } },
                        grid: { display: false },
                        ticks: {
                            stepSize: 20,
                            font: { size: 11 },
                            callback: (v) => v + '%'
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, maxRotation: 45 }
                    }
                }
            }
        });
    }

    window.preparePrintCharts = function() {
        const growthCanvas = document.getElementById('growthReportChartCanvas');
        const envCanvas = document.getElementById('envReportChartCanvas');
        const printGrowthImg = document.getElementById('printGrowthChartImg');
        const printEnvImg = document.getElementById('printEnvChartImg');

        if (growthCanvas && printGrowthImg) {
            try {
                printGrowthImg.src = growthCanvas.toDataURL('image/png');
            } catch (e) {
                console.error('Error generating growth chart image for print:', e);
            }
        }
        if (envCanvas && printEnvImg) {
            try {
                printEnvImg.src = envCanvas.toDataURL('image/png');
            } catch (e) {
                console.error('Error generating env chart image for print:', e);
            }
        }
    };

    window.printReport = function() {
        if (typeof window.preparePrintCharts === 'function') {
            window.preparePrintCharts();
        }
        window.print();
    };

    window.addEventListener('beforeprint', window.preparePrintCharts);

    function syncAllReportCharts() {
        const holder = document.getElementById('reportChartDataHolder');
        if (!holder) return;

        try {
            const labels = JSON.parse(holder.getAttribute('data-labels') || '[]');
            const maggot = JSON.parse(holder.getAttribute('data-maggot') || '[]');
            const feed   = JSON.parse(holder.getAttribute('data-feed') || '[]');
            const temp   = JSON.parse(holder.getAttribute('data-temp') || '[]');
            const humid  = JSON.parse(holder.getAttribute('data-humid') || '[]');

            initOrUpdateGrowthChart(labels, maggot, feed);
            initOrUpdateEnvChart(labels, temp, humid);

            // Pre-render print images
            setTimeout(window.preparePrintCharts, 50);
        } catch (e) {
            console.error('Failed to parse chart data from DOM holder:', e);
        }
    }

    $wire.on('report-charts-updated', (event) => {
        const payload = Array.isArray(event) ? event[0] : event;
        if (payload) {
            initOrUpdateGrowthChart(payload.labels, payload.maggot, payload.feed);
            initOrUpdateEnvChart(payload.labels, payload.temp, payload.humid);
            setTimeout(window.preparePrintCharts, 50);
        }
    });

    $wire.hook('commit', ({ succeed }) => {
        succeed(() => {
            setTimeout(syncAllReportCharts, 20);
        });
    });

    document.addEventListener('livewire:navigated', () => {
        setTimeout(syncAllReportCharts, 20);
    });

    setTimeout(syncAllReportCharts, 20);
</script>
@endscript
