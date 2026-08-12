<div class="p-6 md:p-8 space-y-6 bg-[#F8F9FA] min-h-screen font-sans w-full">

    <!-- Title & Alert -->
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-extrabold text-gray-900">Catatan Pemeliharaan</h1>
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-emerald-100 border border-emerald-300 text-emerald-800 rounded-xl text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" wire:click="$set('message', null)" class="text-emerald-800 font-bold">&times;</button>
        </div>
    @endif

    <!-- Toolbar: Siklus Selector & Button Tambah -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Siklus ke:
            </span>
            <select wire:model.live="selectedCycle" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#1A382B]">
                <option value="">Semua Siklus</option>
                @forelse($cycles as $c)
                    <option value="{{ $c->id }}">{{ sprintf('%02d', $c->cycle_number ?? $c->id) }}</option>
                @empty
                    <option value="1">01</option>
                    <option value="2">02</option>
                    <option value="3">03</option>
                @endforelse
            </select>
        </div>

        <button wire:click="openModal" class="px-5 py-2.5 bg-[#1A382B] hover:bg-[#12281E] text-white font-bold rounded-xl text-sm transition-all shadow-sm flex items-center gap-2 cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Catatan
        </button>
    </div>

    <!-- Tabel Catatan Pemeliharaan -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-center border-collapse">
                <thead>
                    <tr class="bg-[#1A382B] text-white text-sm font-bold">
                        <th class="py-4 px-4 border-r border-[#2d4d3d]">Tanggal</th>
                        <th class="py-4 px-4 border-r border-[#2d4d3d]">Berat Pakan</th>
                        <th class="py-4 px-4 border-r border-[#2d4d3d]">Berat Maggot</th>
                        <th class="py-4 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm font-medium text-gray-700">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-4 border-r border-gray-100">
                                {{ \Carbon\Carbon::parse($log->timestamp)->translatedFormat('l, d F Y') }}
                            </td>
                            <td class="py-4 px-4 border-r border-gray-100 font-semibold text-gray-900">
                                {{ number_format($log->feed_weight, 1) }} kg
                            </td>
                            <td class="py-4 px-4 border-r border-gray-100 font-semibold text-gray-900">
                                {{ number_format($log->maggot_weight, 1) }} kg
                            </td>
                            <td class="py-4 px-4 flex items-center justify-center gap-3">
                                <button wire:click="editLog({{ $log->id }})" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 hover:text-[#1A382B] transition-colors cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    Edit Catatan
                                </button>
                                <button wire:click="confirmDelete({{ $log->id }})" class="inline-flex items-center text-xs font-bold text-red-500 hover:text-red-700 transition-colors cursor-pointer" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-gray-400 text-xs">
                                Belum ada catatan pemeliharaan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4 flex justify-end">
        {{ $logs->links() }}
    </div>

    <!-- Modal Form (Tambah / Edit) -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-md overflow-hidden">
                <div class="bg-[#1A382B] px-6 py-4 flex items-center justify-between text-white">
                    <h3 class="font-bold text-base">
                        {{ $isEditMode ? 'Edit Catatan Pemeliharaan' : 'Tambah Catatan Pemeliharaan' }}
                    </h3>
                    <button wire:click="closeModal" class="text-gray-300 hover:text-white font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="{{ $isEditMode ? 'updateLog' : 'createLog' }}" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Pilih Siklus</label>
                        <select wire:model="cycle_id" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#1A382B] focus:outline-none">
                            <option value="">-- Pilih Siklus --</option>
                            @forelse($cycles as $c)
                                <option value="{{ $c->id }}">Siklus ke-{{ sprintf('%02d', $c->cycle_number ?? $c->id) }}</option>
                            @empty
                                <option value="1">Siklus ke-01 (Dummy)</option>
                                <option value="2">Siklus ke-02 (Dummy)</option>
                                <option value="3">Siklus ke-03 (Dummy)</option>
                            @endforelse
                        </select>
                        @error('cycle_id') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tanggal Catatan</label>
                        <input type="date" wire:model="log_date" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#1A382B] focus:outline-none">
                        @error('log_date') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Berat Pakan (kg)</label>
                        <input type="number" step="0.1" wire:model="feed_weight" placeholder="0.0" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#1A382B] focus:outline-none">
                        @error('feed_weight') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Berat Maggot (kg)</label>
                        <input type="number" step="0.1" wire:model="maggot_weight" placeholder="0.0" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#1A382B] focus:outline-none">
                        @error('maggot_weight') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-xs cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 bg-[#1A382B] hover:bg-[#12281E] text-white font-bold rounded-xl text-xs cursor-pointer">
                            {{ $isEditMode ? 'Simpan Perubahan' : 'Tambah Data' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Konfirmasi Hapus -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-sm p-6 text-center space-y-4">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto text-xl font-bold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h3 class="font-bold text-base text-gray-900">Hapus Catatan Ini?</h3>
                    <p class="text-xs text-gray-500 mt-1">Data yang dihapus tidak dapat dikembalikan lagi.</p>
                </div>
                <div class="flex items-center justify-center gap-3 pt-2">
                    <button wire:click="$set('isDeleteModalOpen', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-xs cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="deleteLog" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-xs cursor-pointer">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>