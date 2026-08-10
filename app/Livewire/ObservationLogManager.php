<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Cycle;
use App\Models\ObservationLog;

#[Layout('components.layouts.app')]
class ObservationLogManager extends Component
{
    public $selectedCycleId;
    public $maggot_weight;
    public $feed_weight;
    public $isModalOpen = false;

    public function mount()
    {
        $activeCycle = Cycle::where('is_active', true)->first();
        $this->selectedCycleId = $activeCycle ? $activeCycle->id : Cycle::first()?->id;
    }

    public function render()
    {
        $cycles = Cycle::all();
        $logs = ObservationLog::where('cycle_id', $this->selectedCycleId)
            ->latest()
            ->get();

        return view('livewire.observation-log-manager', compact('cycles', 'logs'));
    }

    public function openModal()
    {
        $this->resetValidation();
        $this->reset(['maggot_weight', 'feed_weight']);
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
    }

    public function saveLog()
    {
        $this->validate([
            'selectedCycleId' => 'required|exists:cycles,id',
            'maggot_weight'   => 'required|numeric|min:0',
            'feed_weight'     => 'required|numeric|min:0',
        ]);

        ObservationLog::create([
            'cycle_id'      => $this->selectedCycleId,
            'timestamp'     => now()->toDateString(),
            'maggot_weight' => $this->maggot_weight,
            'feed_weight'   => $this->feed_weight,
        ]);

        $this->reset(['maggot_weight', 'feed_weight']);
        $this->isModalOpen = false;
        session()->flash('message', 'Catatan pemeliharaan berhasil ditambahkan!');
    }
}