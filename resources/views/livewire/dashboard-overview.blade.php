<div wire:poll.10s class="p-6 md:p-8 space-y-6 bg-[#F8F9FA] min-h-screen font-sans w-full">

    <!-- Top Status Badges -->
    <div class="flex flex-nowrap items-center gap-3">
        <span class="px-4 py-2 bg-white rounded-xl border border-gray-200 text-xs font-bold text-gray-700 shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-[#1A382B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Siklus ke: {{ sprintf('%02d', $cycleNumber) }}
        </span>
        <span class="px-4 py-2 bg-white rounded-xl border border-gray-200 text-xs font-bold text-gray-700 shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-[#1A382B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            Hari ke: {{ sprintf('%02d', $dayNumber) }}
        </span>
        <span class="px-4 py-2 bg-white rounded-xl border border-gray-200 text-xs font-bold text-gray-700 shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-[#1A382B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
            Fase Sekarang: {{ $currentPhase }}
        </span>
    </div>

    <!-- 3 Cards Summary Utama -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Total Pakan -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#1A382B] text-white rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <span class="text-gray-800 font-extrabold text-lg">Total Pakan Kumulatif</span>
            </div>
            <div class="mt-6">
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-gray-900">{{ number_format($totalFeed, 1) }}</span>
                    <span class="text-lg font-bold text-gray-600">kg</span>
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-semibold rounded-full">+0.0kg dari sebelumnya</span>
                </div>
                <p class="text-[11px] text-gray-400 mt-2">Diperbaharui pada {{ $lastUpdateDate }}</p>
            </div>
        </div>

        <!-- Card 2: Berat Maggot -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#1A382B] text-white rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
                <span class="text-gray-800 font-extrabold text-lg">Berat Maggot</span>
            </div>
            <div class="mt-6">
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-gray-900">{{ number_format($latestMaggotWeight, 1) }}</span>
                    <span class="text-lg font-bold text-gray-600">kg</span>
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-semibold rounded-full">+0.0kg dari sebelumnya</span>
                </div>
                <p class="text-[11px] text-gray-400 mt-2">Diperbaharui pada {{ $lastUpdateDate }}</p>
            </div>
        </div>

        <!-- Card 3: Konversi Rasio Pakan -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#1A382B] text-white rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <span class="text-gray-800 font-extrabold text-lg">Konversi Rasio Pakan Sementara</span>
            </div>
            <div class="mt-6">
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-gray-900">{{ $fcr }}</span>
                    <span class="text-xs font-semibold text-gray-500">per kg maggot</span>
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-semibold rounded-full">+0.0 dari sebelumnya</span>
                </div>
                <p class="text-[11px] text-gray-400 mt-2">Diperbaharui pada {{ $lastUpdateDate }}</p>
            </div>
        </div>
    </div>

    <!-- Section Grafik & Aktivitas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Box Grafik Suhu -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#1A382B] text-white rounded-xl flex items-center justify-center shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Suhu</h3>
                </div>
                <div class="flex items-center gap-3 mt-3">
                    <span class="text-3xl font-extrabold text-gray-900">{{ number_format($tempVal, 1) }}°C</span>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">Normal</span>
                </div>
                <p class="text-[11px] text-gray-400 mt-1 mb-4">
                    Diperbaharui pada {{ $latestEnv ? \Carbon\Carbon::parse($latestEnv->created_at)->translatedFormat('l, d F Y - H:i:s') : '-' }}
                </p>

                <div class="relative w-full h-64 border border-gray-200 rounded-xl p-4 bg-gray-50/50" wire:ignore>
                    <canvas x-data="{
                        chart: null,
                        updateChart() {
                            let labels = {{ json_encode($chartLabels) }};
                            let data = {{ json_encode($chartTemp) }};
                            
                            let existingChart = Chart.getChart(this.$el);
                            if (existingChart) existingChart.destroy();

                            this.chart = new Chart(this.$el.getContext('2d'), {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Suhu',
                                        data: data,
                                        borderColor: '#1A382B',
                                        backgroundColor: 'rgba(26, 56, 43, 0.08)',
                                        borderWidth: 2.5,
                                        pointRadius: 4,
                                        pointHoverRadius: 7,
                                        pointBackgroundColor: '#1A382B',
                                        tension: 0.3,
                                        fill: true
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            enabled: true,
                                            backgroundColor: '#1A382B',
                                            callbacks: {
                                                label: (ctx) => ' Suhu: ' + ctx.parsed.y.toFixed(1) + '°C'
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            ticks: {
                                                maxTicksLimit: 6,
                                                callback: (v) => v.toFixed(1) + '°C'
                                            },
                                            grid: { color: '#F3F4F6' }
                                        },
                                        x: { grid: { display: false } }
                                    }
                                }
                            });
                        }
                    }"
                    x-init="updateChart()"
                    x-on:livewire:updated.window="updateChart()"
                    style="width: 100%; height: 100%;"></canvas>
                </div>
            </div>

            <!-- Box Grafik Kelembapan -->
            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#1A382B] text-white rounded-xl flex items-center justify-center shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Kelembapan</h3>
                </div>
                <div class="flex items-center gap-3 mt-3">
                    <span class="text-3xl font-extrabold text-gray-900">{{ number_format($humidVal, 1) }}%</span>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">Normal</span>
                </div>
                <p class="text-[11px] text-gray-400 mt-1 mb-4">
                    Diperbaharui pada {{ $latestEnv ? \Carbon\Carbon::parse($latestEnv->created_at)->translatedFormat('l, d F Y - H:i:s') : '-' }}
                </p>

                <div class="relative w-full h-64 border border-gray-200 rounded-xl p-4 bg-gray-50/50" wire:ignore>
                    <canvas x-data="{
                        chart: null,
                        updateChart() {
                            let labels = {{ json_encode($chartLabels) }};
                            let data = {{ json_encode($chartHumid) }};

                            let existingChart = Chart.getChart(this.$el);
                            if (existingChart) existingChart.destroy();

                            this.chart = new Chart(this.$el.getContext('2d'), {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Kelembapan',
                                        data: data,
                                        borderColor: '#1A382B',
                                        backgroundColor: 'rgba(26, 56, 43, 0.08)',
                                        borderWidth: 2.5,
                                        pointRadius: 4,
                                        pointHoverRadius: 7,
                                        pointBackgroundColor: '#1A382B',
                                        tension: 0.3,
                                        fill: true
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            enabled: true,
                                            backgroundColor: '#1A382B',
                                            callbacks: {
                                                label: (ctx) => ' Kelembapan: ' + ctx.parsed.y.toFixed(1) + '%'
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            ticks: {
                                                maxTicksLimit: 6,
                                                callback: (v) => v.toFixed(1) + '%'
                                            },
                                            grid: { color: '#F3F4F6' }
                                        },
                                        x: { grid: { display: false } }
                                    }
                                }
                            });
                        }
                    }"
                    x-init="updateChart()"
                    x-on:livewire:updated.window="updateChart()"
                    style="width: 100%; height: 100%;"></canvas>
                </div>
            </div>
        </div>

        <!-- Sidebar Aktivitas -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm h-fit space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 bg-[#1A382B] text-white rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Aktivitas</h3>
            </div>

            <!-- List Alert / Aktivitas -->
            <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl flex items-start gap-3">
                <div class="p-2 bg-amber-100 text-amber-800 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-gray-900">Suhu terlalu rendah</h4>
                    <p class="text-xs text-gray-500">Suhu mencapai 25°C</p>
                    <span class="text-[10px] text-gray-400 mt-1 block">1 Jam yang Lalu</span>
                </div>
            </div>

            <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl flex items-start gap-3">
                <div class="p-2 bg-red-100 text-red-800 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-gray-900">Suhu terlalu tinggi</h4>
                    <p class="text-xs text-gray-500">Suhu mencapai 35°C</p>
                    <span class="text-[10px] text-gray-400 mt-1 block">2 Jam yang Lalu</span>
                </div>
            </div>
        </div>
    </div>
</div>