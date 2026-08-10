<div class="p-6 bg-[#F8F9FA] min-h-screen font-sans">
    <h1 class="text-3xl font-extrabold text-gray-900 mb-6">Catatan Pemeliharaan</h1>

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-2xl px-4 py-2 shadow-sm">
            <span class="font-bold text-gray-700 text-sm">Siklus ke:</span>
            <select wire:model.live="selectedCycleId" class="border-0 font-extrabold text-[#1A382B] text-lg focus:ring-0 cursor-pointer bg-transparent">
                @foreach($cycles as $cycle)
                    <option value="{{ $cycle->id }}">{{ sprintf('%02d', $cycle->id) }}</option>
                @endforeach
            </select>
        </div>

        <button wire:click="$set('isModalOpen', true)" class="bg-[#1A382B] hover:bg-[#12281e] text-white px-6 py-3 rounded-2xl font-bold text-sm flex items-center gap-2 shadow-sm transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Catatan
        </button>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-emerald-100 border border-emerald-300 text-emerald-800 rounded-2xl text-sm font-semibold">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 bg-[#1A382B] text-white font-bold text-lg flex items-center gap-3">
            <span>📋</span> Riwayat Catatan
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#1A382B] text-white text-sm font-semibold">
                        <th class="p-4 border-r border-[#2d5643] text-center w-1/3">Tanggal</th>
                        <th class="p-4 border-r border-[#2d5643] text-center w-1/3">Berat Pakan</th>
                        <th class="p-4 text-center w-1/3">Berat Maggot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50 transition text-center text-gray-700">
                            <td class="p-4 border-r border-gray-100 font-medium">
                                {{ $log->created_at->translatedFormat('l, d F Y') }}
                            </td>
                            <td class="p-4 border-r border-gray-100 font-medium">
                                {{ number_format($log->feed_weight, 2) }} kg
                            </td>
                            <td class="p-4 font-medium">
                                {{ number_format($log->maggot_weight, 2) }} kg
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-12 text-center text-gray-400 font-medium">
                                Belum ada catatan pemeliharaan untuk siklus ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($isModalOpen)
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-extrabold text-gray-900">Tambah Catatan Pemeliharaan</h3>
                    <button wire:click="$set('isModalOpen', false)" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form wire:submit.prevent="saveLog" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Berat Pakan (kg)</label>
                        <input type="number" step="0.01" wire:model="feed_weight" class="w-full border border-gray-300 rounded-2xl p-3 focus:ring-2 focus:ring-[#1A382B] focus:outline-none" placeholder="0.00" required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Berat Maggot (kg)</label>
                        <input type="number" step="0.01" wire:model="maggot_weight" class="w-full border border-gray-300 rounded-2xl p-3 focus:ring-2 focus:ring-[#1A382B] focus:outline-none" placeholder="0.00" required>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-5 py-2.5 rounded-2xl border border-gray-300 text-gray-600 font-bold text-sm hover:bg-gray-50 transition">Batal</button>
                        <button type="submit" class="px-5 py-2.5 bg-[#1A382B] hover:bg-[#12281e] text-white rounded-2xl font-bold text-sm shadow-sm transition">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>