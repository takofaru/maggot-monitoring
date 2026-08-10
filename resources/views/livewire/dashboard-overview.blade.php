<div wire:poll.3s class="p-6 space-y-6 bg-[#F8F9FA] min-h-screen font-sans">
    <!-- Section Status Bar -->
    <div class="flex flex-wrap items-center gap-4">
        <span class="px-4 py-2 bg-white rounded-full border border-gray-200 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Siklus ke: {{ sprintf('%02d', $cycleNumber) }}
        </span>
        <span class="px-4 py-2 bg-white rounded-full border border-gray-200 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            Hari ke: {{ sprintf('%02d', $dayNumber) }}
        </span>
        <span class="px-4 py-2 bg-white rounded-full border border-gray-200 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
            Fase Sekarang: {{ $currentPhase }}
        </span>
    </div>

    <!-- Section Card Statistik 3 Kolom -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Total Pakan Kumulatif -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-[#1A382B] text-white rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 18h12l3-18H3z"></path></svg>
                </div>
                <span class="text-gray-600 font-bold text-lg">Total Pakan Kumulatif</span>
            </div>
            <div class="mt-6">
                <div class="flex items-baseline gap-1">
                    <span class="text-4xl font-extrabold text-gray-900">{{ number_format($totalFeed, 1) }}</span>
                    <span class="text-xl font-bold text-gray-600">kg</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Diperbaharui pada {{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>

        <!-- Card 2: Berat Maggot -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-[#1A382B] text-white rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
                <span class="text-gray-600 font-bold text-lg">Berat Maggot</span>
            </div>
            <div class="mt-6">
                <div class="flex items-baseline gap-1">
                    <span class="text-4xl font-extrabold text-gray-900">{{ number_format($latestMaggotWeight, 1) }}</span>
                    <span class="text-xl font-bold text-gray-600">kg</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Diperbaharui pada {{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>

        <!-- Card 3: Konversi Rasio Pakan (FCR) -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-[#1A382B] text-white rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <span class="text-gray-600 font-bold text-lg">Konversi Rasio Pakan</span>
            </div>
            <div class="mt-6">
                <div class="flex items-baseline gap-1">
                    <span class="text-4xl font-extrabold text-gray-900">{{ $fcr }}</span>
                    <span class="text-sm font-semibold text-gray-500">per kg maggot</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Diperbaharui pada {{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>
    </div>

    <!-- Section Live Sensor & Dashboard Right Sidebar -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @php
            $latestEnv = $envLogs->last();
            $tempVal = $latestEnv ? $latestEnv->temperature : 0;
            $humidVal = $latestEnv ? $latestEnv->humidity : 0;
            
            // Logika Status Sederhana
            $tempStatus = ($tempVal >= 25 && $tempVal <= 35) ? 'Normal' : ($tempVal < 25 ? 'Terlalu Rendah' : 'Terlalu Tinggi');
            $humidStatus = ($humidVal >= 60 && $humidVal <= 85) ? 'Normal' : ($humidVal < 60 ? 'Terlalu Rendah' : 'Terlalu Tinggi');
        @endphp

        <!-- Monitoring Suhu & Kelembapan (2 Kolom) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Box Suhu -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                <div class="flex justify-between items-center mb-1">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-[#1A382B] text-white rounded-lg">🌡️</div>
                        <h3 class="text-xl font-bold text-gray-800">Suhu</h3>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-2">
                    <span class="text-3xl font-extrabold text-gray-900">{{ number_format($tempVal, 1) }}°C</span>
                    <span class="px-3 py-1 {{ $tempStatus == 'Normal' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }} text-xs font-bold rounded-full">
                        {{ $tempStatus }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1 mb-4">Diperbaharui pada {{ now()->translatedFormat('l, d F Y - H:i:s') }}</p>

                <!-- Ringkasan Log Suhu Terakhir -->
                <div class="p-4 bg-gray-50 border border-gray-100 rounded-xl space-y-2">
                    <div class="flex justify-between text-xs font-bold text-gray-500 border-b pb-2 border-gray-200">
                        <span>Waktu Log</span>
                        <span>Suhu (°C)</span>
                    </div>
                    @forelse($envLogs->take(4) as $log)
                        <div class="flex justify-between text-xs text-gray-700">
                            <span>{{ $log->timestamp ?? $log->created_at }}</span>
                            <span class="font-bold text-red-600">{{ number_format($log->temperature, 1) }} °C</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-2">Belum ada data dari simulator.</p>
                    @endforelse
                </div>
            </div>

            <!-- Box Kelembapan -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                <div class="flex justify-between items-center mb-1">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-[#1A382B] text-white rounded-lg">💧</div>
                        <h3 class="text-xl font-bold text-gray-800">Kelembapan</h3>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-2">
                    <span class="text-3xl font-extrabold text-gray-900">{{ number_format($humidVal, 1) }}%</span>
                    <span class="px-3 py-1 {{ $humidStatus == 'Normal' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }} text-xs font-bold rounded-full">
                        {{ $humidStatus }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1 mb-4">Diperbaharui pada {{ now()->translatedFormat('l, d F Y - H:i:s') }}</p>

                <!-- Ringkasan Log Kelembapan Terakhir -->
                <div class="p-4 bg-gray-50 border border-gray-100 rounded-xl space-y-2">
                    <div class="flex justify-between text-xs font-bold text-gray-500 border-b pb-2 border-gray-200">
                        <span>Waktu Log</span>
                        <span>Kelembapan (%)</span>
                    </div>
                    @forelse($envLogs->take(4) as $log)
                        <div class="flex justify-between text-xs text-gray-700">
                            <span>{{ $log->timestamp ?? $log->created_at }}</span>
                            <span class="font-bold text-blue-600">{{ number_format($log->humidity, 1) }} %</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-2">Belum ada data dari simulator.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar Aktivitas (1 Kolom) -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm h-fit">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-[#1A382B] text-white rounded-lg">📈</div>
                <h3 class="text-xl font-bold text-gray-800">Aktivitas Terkini</h3>
            </div>

            <div class="space-y-4">
                @if($latestEnv)
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl flex items-start gap-3">
                        <span class="text-2xl">📡</span>
                        <div>
                            <h4 class="font-bold text-sm text-gray-900">Telemetri Baru Diterima</h4>
                            <p class="text-xs text-gray-500">
                                Suhu: {{ number_format($latestEnv->temperature, 1) }}°C, Kelembapan: {{ number_format($latestEnv->humidity, 1) }}%
                            </p>
                            <span class="text-[10px] text-gray-400 mt-1 block">{{ $latestEnv->timestamp ?? $latestEnv->created_at }}</span>
                        </div>
                    </div>
                @else
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl text-center">
                        <p class="text-xs text-gray-400">Menunggu aktivitas simulator...</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>