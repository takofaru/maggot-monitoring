<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ObservationLog;
use App\Models\Cycle;

class MaintenanceManager extends Component
{
    use WithPagination;

    public $selectedCycle = '';

    // Form Fields
    public $log_id;
    public $cycle_id = 1;
    public $log_date;
    public $feed_weight;
    public $maggot_weight;

    // Modal Control Flags
    public $isModalOpen = false;
    public $isDeleteModalOpen = false;
    public $isEditMode = false;
    public $logIdToDelete;

    protected $paginationTheme = 'tailwind';

    protected function rules()
    {
        return [
            'cycle_id'      => 'required',
            'log_date'      => 'required|date',
            'feed_weight'   => 'required|numeric|min:0',
            'maggot_weight' => 'nullable|numeric|min:0',
        ];
    }

    protected $messages = [
        'cycle_id.required'    => 'Silakan pilih siklus terlebih dahulu.',
        'feed_weight.required' => 'Berat pakan wajib diisi.',
    ];

    public function mount()
    {
        $this->log_date = date('Y-m-d');
        $activeCycle = Cycle::where('is_active', true)->first() ?? Cycle::latest()->first();
        if ($activeCycle) {
            $this->selectedCycle = $activeCycle->id;
            $this->cycle_id = $activeCycle->id;
        } else {
            $this->cycle_id = 1;
        }
    }

    public function openModal()
    {
        $this->resetForm();
        $this->isEditMode = false;
        
        $activeCycle = Cycle::where('is_active', true)->first() ?? Cycle::latest()->first();
        if ($activeCycle) {
            $this->cycle_id = $activeCycle->id;
        } else {
            $this->cycle_id = 1;
        }

        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['log_id', 'feed_weight', 'maggot_weight', 'isEditMode']);
        $this->resetValidation();
        $this->log_date = date('Y-m-d');
        
        $activeCycle = Cycle::where('is_active', true)->first() ?? Cycle::latest()->first();
        $this->cycle_id = $activeCycle ? $activeCycle->id : 1;
    }

    public function createLog()
    {
        $this->validate();

        $timestampValue = $this->log_date ? $this->log_date . ' ' . date('H:i:s') : now();

        // Buat siklus otomatis jika belum ada di database, lengkap dengan end_date
        if (!Cycle::find($this->cycle_id)) {
            $createdCycle = Cycle::create([
                'cycle_number'  => (int) $this->cycle_id,
                'start_date'    => now()->toDateString(),
                'end_date'      => now()->addDays(30)->toDateString(), // Mengisi end_date agar tidak NULL
                'current_phase' => 'Penetasan',
                'is_active'     => true,
            ]);
            $this->cycle_id = $createdCycle->id;
        }

        // Menyimpan data ke tabel observation_logs
        ObservationLog::create([
            'cycle_id'      => $this->cycle_id,
            'timestamp'     => $timestampValue,
            'feed_weight'   => $this->feed_weight,
            'maggot_weight' => $this->maggot_weight ?? 0,
        ]);

        session()->flash('message', 'Catatan pemeliharaan berhasil ditambahkan.');
        $this->closeModal();
    }

    public function editLog($id)
    {
        $log = ObservationLog::findOrFail($id);
        $this->log_id = $log->id;
        $this->cycle_id = $log->cycle_id;
        $this->log_date = $log->timestamp ? date('Y-m-d', strtotime($log->timestamp)) : date('Y-m-d');
        $this->feed_weight = $log->feed_weight;
        $this->maggot_weight = $log->maggot_weight;

        $this->isEditMode = true;
        $this->isModalOpen = true;
    }

    public function updateLog()
    {
        $this->validate();

        if ($this->log_id) {
            $log = ObservationLog::findOrFail($this->log_id);
            $timestampValue = $this->log_date ? $this->log_date . ' ' . date('H:i:s') : now();

            $log->update([
                'cycle_id'      => $this->cycle_id,
                'timestamp'     => $timestampValue,
                'feed_weight'   => $this->feed_weight,
                'maggot_weight' => $this->maggot_weight ?? 0,
            ]);

            session()->flash('message', 'Catatan pemeliharaan berhasil diperbarui.');
            $this->closeModal();
        }
    }

    public function confirmDelete($id)
    {
        $this->logIdToDelete = $id;
        $this->isDeleteModalOpen = true;
    }

    public function deleteLog()
    {
        if ($this->logIdToDelete) {
            ObservationLog::destroy($this->logIdToDelete);
            session()->flash('message', 'Catatan berhasil dihapus.');
            $this->isDeleteModalOpen = false;
            $this->logIdToDelete = null;
        }
    }

    public function render()
    {
        $cycles = Cycle::orderBy('cycle_number', 'asc')->get();

        $query = ObservationLog::query();
        if ($this->selectedCycle) {
            $query->where('cycle_id', $this->selectedCycle);
        }

        $logs = $query->latest('id')->paginate(10);

        return view('livewire.maintenance-manager', [
            'logs'   => $logs,
            'cycles' => $cycles,
        ]);
    }
}