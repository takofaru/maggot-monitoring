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

            // Hitung siklus yang terjadi pada rentang periode ini (Logika Lengkap vs Separuh)
            $allCycles = Cycle::all();
            $logCycleIds = $logs->pluck('cycle_id')->filter()->unique();
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
            $involvedCycles = Cycle::whereIn('id', $involvedCycleIds->unique())->get();

            $completedCount = 0;
            $partialCount = 0;
            foreach ($involvedCycles as $c) {
                $cStart = $c->start_date ? Carbon::parse($c->start_date)->startOfDay() : null;
                $cEnd = $c->end_date ? Carbon::parse($c->end_date)->endOfDay() : null;

                // Siklus Selesai Penuh: Harus dimulai pada/setelah start DAN selesai pada/sebelum end
                $isFullCompleted = ($cStart !== null && $cStart >= $start && $cEnd !== null && $cEnd <= $end && (!$c->is_active || $c->current_phase === 'panen'));

                if ($isFullCompleted) {
                    $completedCount++;
                } else {
                    $partialCount++;
                }
            }

            $filename = "laporan_periodik_" . $start->format('Ymd') . "_sd_" . $end->format('Ymd') . ".csv";

            return response()->streamDownload(function () use ($logs, $start, $end, $completedCount, $partialCount) {
                $handle = fopen('php://output', 'w');
                fputs($handle, "\xEF\xBB\xBF");

                fputcsv($handle, ["LAPORAN PERIODIK REKAPITULASI BUDIDAYA MAGGOT"], ',', '"', "\\");
                fputcsv($handle, ["Rentang Periode", $start->format('d/m/Y') . " s/d " . $end->format('d/m/Y')], ',', '"', "\\");
                fputcsv($handle, ["Siklus Selesai Penuh", "{$completedCount} siklus (Dimulai & Panen di periode ini)"], ',', '"', "\\");
                fputcsv($handle, ["Siklus Separuh", "{$partialCount} siklus (Sebagian periode / sedang berjalan)"], ',', '"', "\\");
                fputcsv($handle, ["Jumlah Log Observasi", $logs->count() . " data"], ',', '"', "\\");
                fputcsv($handle, ["Tanggal Unduh", now()->translatedFormat('d F Y H:i:s')], ',', '"', "\\");
                fputcsv($handle, [], ',', '"', "\\");

                fputcsv($handle, [
                    'No',
                    'Tanggal & Waktu',
                    'Siklus',
                    'Fase',
                    'Suhu (°C)',
                    'Kelembapan (%)',
                    'Pakan Diberikan (kg)',
                    'Berat Maggot (kg)',
                    'Catatan Tambahan',
                ], ',', '"', "\\");

                foreach ($logs as $index => $log) {
                    fputcsv($handle, [
                        $index + 1,
                        $log->timestamp ? $log->timestamp->format('d/m/Y H:i') : '-',
                        $log->cycle ? "Siklus {$log->cycle->id}" : '-',
                        ucfirst($log->phase_name),
                        $log->environmentLog?->temperature ?? '-',
                        $log->environmentLog?->humidity ?? '-',
                        number_format((float) $log->feed_weight, 2, '.', ''),
                        number_format((float) $log->maggot_weight, 2, '.', ''),
                        $log->notes ?? '-',
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
        $cycle = Cycle::find($cycleId);
        $logs = ObservationLog::with('environmentLog')
            ->where('cycle_id', $cycleId)
            ->orderBy('timestamp', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $filename = "laporan_siklus_{$cycleId}_" . now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($logs, $cycle) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ["LAPORAN REKAPITULASI BUDIDAYA MAGGOT - SIKLUS {$cycle?->id}"], ',', '"', "\\");
            fputcsv($handle, ["Tanggal Mulai", $cycle?->start_date?->format('d/m/Y') ?? 'Belum Dimulai'], ',', '"', "\\");
            fputcsv($handle, ["Tanggal Selesai", $cycle?->end_date?->format('d/m/Y') ?? ($cycle?->is_active ? 'Sedang Berjalan' : '-')], ',', '"', "\\");
            fputcsv($handle, ["Status", $cycle?->is_active ? 'Aktif' : 'Panen / Selesai'], ',', '"', "\\");
            fputcsv($handle, [], ',', '"', "\\");

            fputcsv($handle, [
                'No',
                'Tanggal',
                'Fase',
                'Suhu (°C)',
                'Kelembapan (%)',
                'Pakan Diberikan (kg)',
                'Berat Maggot (kg)',
                'Catatan Tambahan',
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
                    $log->notes ?? '-',
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
                    // Tentukan alasan separuh untuk transparansi pengguna
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
            'observationLogs'  => ObservationLog::with(['environmentLog', 'cycle'])
                ->where('cycle_id', $this->selectedCycleId)
                ->orderBy('timestamp', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(10),
        ];
    }
};
?>

<div class="space-y-(--size-26) w-full">
    <!-- Header Halaman & Tombol Lonceng Notifikasi -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold leading-tight">
                Laporan Budidaya
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Rekapitulasi performa, konsumsi pakan, pertumbuhan bobot, dan evaluasi lingkungan.
            </p>
        </div>
        <livewire:notification-bell />
    </div>

    <!-- Mode Selector Tabs & Toolbar Filter -->
    <div class="flex flex-col gap-3 w-full">
        <!-- Baris 1: Mode Switch Tab & Aksi Ekspor -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="inline-flex h-[58px] p-1.5 bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] items-center gap-1.5 shadow-xs shrink-0">
                <button
                    type="button"
                    wire:click="setReportMode('periodic')"
                    class="h-full px-5 rounded-xl font-semibold text-sm transition-all cursor-pointer flex items-center gap-2 {{ $reportMode === 'periodic' ? 'bg-(--prime-colour) text-(--fg-colour) shadow-xs' : 'text-(--text-colour) hover:bg-gray-100' }}"
                >
                    <x-lucide-calendar-range class="w-4 h-4"/>
                    <span>Laporan Periodik</span>
                </button>
                <button
                    type="button"
                    wire:click="setReportMode('cycle')"
                    class="h-full px-5 rounded-xl font-semibold text-sm transition-all cursor-pointer flex items-center gap-2 {{ $reportMode === 'cycle' ? 'bg-(--prime-colour) text-(--fg-colour) shadow-xs' : 'text-(--text-colour) hover:bg-gray-100' }}"
                >
                    <x-lucide-refresh-cw class="w-4 h-4"/>
                    <span>Laporan Siklus</span>
                </button>
            </div>

            <!-- Tombol Ekspor CSV & Cetak Laporan -->
            <div class="flex flex-row items-center gap-(--size-10) flex-nowrap">
                <button
                    wire:click="exportCsv"
                    type="button"
                    class="h-[58px] gap-(--size-10) px-(--size-26) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) font-medium text-(length:--size-16) cursor-pointer hover:opacity-90 flex items-center whitespace-nowrap shrink-0 shadow-xs"
                >
                    <x-lucide-download class="w-(--size-26)"/>
                    <span>Ekspor CSV</span>
                </button>

                <button
                    onclick="window.print()"
                    type="button"
                    class="h-[58px] gap-(--size-10) px-(--size-26) bg-(--prime-colour) text-(--fg-colour) rounded-(--size-16) font-medium text-(length:--size-16) cursor-pointer hover:opacity-90 flex items-center whitespace-nowrap shrink-0 shadow-xs"
                >
                    <x-lucide-printer class="w-(--size-26)"/>
                    <span>Cetak Laporan</span>
                </button>
            </div>
        </div>

        <!-- Baris 2: Filter Toolbar Sesuai Mode Terpilih -->
        <div class="flex items-center justify-between w-full flex-wrap gap-3">
            @if($reportMode === 'periodic')
                <!-- Toolbar Mode Periodik -->
                <div class="flex flex-row items-center gap-(--size-10) flex-wrap">
                    <!-- Preset Cepat Periodik -->
                    <div class="inline-flex h-[58px] gap-(--size-10) items-center px-(--size-16) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs whitespace-nowrap shrink-0">
                        <span class="text-sm font-semibold text-gray-500">Preset:</span>
                        <div class="flex items-center gap-1.5">
                            <button
                                wire:click="setPeriodicPreset('7days')"
                                type="button"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {{ $periodicPreset === '7days' ? 'bg-(--prime-colour) text-(--fg-colour)' : 'bg-(--bg-colour) hover:bg-gray-200 text-gray-700' }}"
                            >
                                7 Hari
                            </button>
                            <button
                                wire:click="setPeriodicPreset('30days')"
                                type="button"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {{ $periodicPreset === '30days' ? 'bg-(--prime-colour) text-(--fg-colour)' : 'bg-(--bg-colour) hover:bg-gray-200 text-gray-700' }}"
                            >
                                30 Hari
                            </button>
                            <button
                                wire:click="setPeriodicPreset('this_month')"
                                type="button"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {{ $periodicPreset === 'this_month' ? 'bg-(--prime-colour) text-(--fg-colour)' : 'bg-(--bg-colour) hover:bg-gray-200 text-gray-700' }}"
                            >
                                Bulan Ini
                            </button>
                        </div>
                    </div>

                    <!-- Input Rentang Tanggal Manual (Dari - Sampai) -->
                    <div class="inline-flex h-[58px] gap-3 items-center px-(--size-16) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs whitespace-nowrap shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-gray-500">Dari:</span>
                            <input
                                type="date"
                                wire:model.live="startDate"
                                class="bg-(--bg-colour) border border-(--outline-colour) rounded-lg px-2.5 py-1 text-sm font-medium text-(--text-colour) focus:outline-none focus:ring-1 focus:ring-(--prime-colour) cursor-pointer"
                            />
                        </div>
                        <span class="text-gray-300 font-bold">&mdash;</span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-gray-500">Sampai:</span>
                            <input
                                type="date"
                                wire:model.live="endDate"
                                class="bg-(--bg-colour) border border-(--outline-colour) rounded-lg px-2.5 py-1 text-sm font-medium text-(--text-colour) focus:outline-none focus:ring-1 focus:ring-(--prime-colour) cursor-pointer"
                            />
                        </div>
                    </div>

                    <!-- Pill Ringkasan Periode Terpilih -->
                    <div class="inline-flex h-[58px] gap-(--size-10) items-center px-(--size-16) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs text-(length:--size-16) whitespace-nowrap shrink-0">
                        <div class="gap-(--size-6) flex items-center">
                            <x-lucide-calendar class="w-4 h-4 text-(--prime-colour)"/>
                            <span class="font-bold text-(--prime-colour) ml-1">
                                {{ Carbon::parse($startDate)->translatedFormat('d M Y') }} &mdash; {{ Carbon::parse($endDate)->translatedFormat('d M Y') }}
                            </span>
                            <span class="text-xs text-gray-400 ml-1">({{ $durationDays }} Hari)</span>
                        </div>
                    </div>
                </div>
            @else
                <!-- Toolbar Mode Siklus -->
                <div class="flex flex-row items-center gap-(--size-10) flex-wrap">
                    <!-- Dropdown Pilihan Siklus -->
                    <div x-data="{ openDropdown: false }" class="inline-flex h-[58px] gap-(--size-10) items-center px-(--size-16) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs whitespace-nowrap shrink-0">
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
                                            {{ $item->start_date ? $item->start_date->translatedFormat('d M Y') : 'Belum Dimulai' }} &mdash; {{ $item->end_date ? $item->end_date->translatedFormat('d M Y') : 'Sekarang' }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Status Siklus Pill -->
                    <div class="inline-flex h-[58px] gap-(--size-10) items-center px-(--size-16) bg-(--fg-colour) border-(--outline-colour) rounded-(--size-16) border-[1.5px] shadow-xs text-(length:--size-16) whitespace-nowrap shrink-0">
                        <div class="gap-(--size-6) flex items-center">
                            <span>Status:</span>
                            <span class="font-bold text-(--prime-colour) ml-1">
                                {{ $currentCycle?->is_active ? 'Aktif (' . ucfirst($currentCycle->current_phase) . ')' : 'Selesai / Panen' }}
                            </span>
                            <span class="text-xs text-gray-400 ml-1">({{ $currentCycle?->start_date ? $durationDays . ' Hari' : 'Belum Dimulai' }})</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- 3 Kartu Ringkasan KPI Utama (Seragam 100% Ukuran & Proporsinya antara Mode Periodik dan Mode Siklus) -->
    <div class="grid grid-cols-3 gap-(--size-26) w-full">
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

    <!-- Rekapitulasi Siklus dalam Periode (Khusus Mode Periodik: Kontainer Luas & Rinci) -->
    @if($reportMode === 'periodic')
        <div class="flex flex-col gap-(--size-16) px-(--size-26) py-(--size-26) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) shadow-xs">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <x-lucide-refresh-cw class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
                    <div>
                        <h2 class="text-(--prime-colour) text-(length:--size-26) font-bold leading-tight">
                            Rekapitulasi Siklus dalam Periode (Total {{ $totalInvolvedCycles }} Siklus)
                        </h2>
                        <p class="text-xs text-gray-400">
                            Klasifikasi siklus yang berjalan penuh vs siklus separuh/sebagian pada rentang tanggal ini
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
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

            <!-- Grid Kartu Siklus -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
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
                            <div class="text-[11px] text-gray-500 italic pt-1">
                                &bull; {{ $item['detail'] }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-8 text-center text-sm text-gray-400 bg-(--bg-colour) rounded-xl border border-dashed border-gray-300">
                        Tidak ada aktivitas siklus yang teridentifikasi dalam rentang waktu tanggal ini.
                    </div>
                @endforelse
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

        <div class="overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) w-full shadow-xs mt-2">
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

    <!-- Tabel 2: Rincian Log Catatan Observasi -->
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

        <div class="overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) w-full shadow-xs mt-2">
            <table class="w-full text-left border-collapse">
                <thead class="border-b-[1.5px] border-(--prime-light-colour) bg-(--prime-colour)">
                    <tr>
                        <th class="min-w-[180px]">Tanggal & Waktu</th>
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
                        <tr class="border-b-[1.5px] border-(--outline-colour) hover:bg-gray-50 transition-colors">
                            <td>{{ $item->timestamp ? $item->timestamp->translatedFormat('d F Y - H:i') : '-' }}</td>
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
