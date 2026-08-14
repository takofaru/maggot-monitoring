<div class="p-6 md:p-8 space-y-6 bg-[#F8F9FA] min-h-screen font-sans w-full">

    <!-- Title & Toolbar -->
    <div class="flex flex-nowrap items-center justify-between gap-4">
        <h1 class="text-3xl font-extrabold text-gray-900">Laporan dan Analisis</h1>

        <!-- Filter Siklus (Jika Dibutuhkan) -->
        @if(isset($cycles) && count($cycles) > 0)
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-gray-700">Siklus ke:</span>
                <select wire:model.live="selectedCycle" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-800 shadow-xs focus:outline-none focus:ring-2 focus:ring-[#1A382B]">
                    <option value="">Semua Siklus</option>
                    @foreach($cycles as $c)
                        <option value="{{ $c->id }}">{{ sprintf('%02d', $c->cycle_number ?? $c->id) }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <!-- Tabel Laporan -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-center border-collapse">
                <thead>
                    <tr class="bg-[#1A382B] text-white text-sm font-bold">
                        <th class="py-4 px-4 border-r border-[#2d4d3d]">Tanggal</th>
                        <th class="py-4 px-4 border-r border-[#2d4d3d]">Berat Pakan</th>
                        <th class="py-4 px-4">Berat Maggot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm font-medium text-gray-700">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <!-- MENGAMBIL TANGGAL ASLI DARI DATABASE -->
                            <td class="py-4 px-4 border-r border-gray-100">
                                {{ \Carbon\Carbon::parse($log->timestamp ?? $log->created_at)->translatedFormat('l, d F Y') }}
                            </td>
                            <td class="py-4 px-4 border-r border-gray-100 font-semibold text-gray-900">
                                {{ number_format($log->feed_weight, 1) }} kg
                            </td>
                            <td class="py-4 px-4 font-semibold text-gray-900">
                                {{ number_format($log->maggot_weight, 1) }} kg
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-8 text-center text-gray-400 text-xs">
                                Belum ada data laporan.
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

</div>