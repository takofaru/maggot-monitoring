<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ObservationLog;
use App\Models\Cycle;

class ReportsManager extends Component
{
    use WithPagination;

    public $selectedCycle = '';

    protected $paginationTheme = 'tailwind';

    public function render()
    {
        $cycles = Cycle::orderBy('cycle_number', 'asc')->get();

        $query = ObservationLog::query();

        if ($this->selectedCycle) {
            $query->where('cycle_id', $this->selectedCycle);
        }

        // Ambil data dari yang terbaru
        $logs = $query->latest('id')->paginate(10);

        return view('livewire.reports-manager', [
            'logs'   => $logs,
            'cycles' => $cycles,
        ]);
    }
}