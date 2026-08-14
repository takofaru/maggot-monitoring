<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public bool $isAdmin = false;

    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            $this->isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');
        }
    }
};
?>

<aside
    x-data="{
        hoverMode: false,
        isHovered: false,
        toggleHoverMode() {
            this.hoverMode = !this.hoverMode;
            localStorage.setItem('maggot_sidebar_hover', this.hoverMode ? 'true' : 'false');
        },
        init() {
            this.hoverMode = localStorage.getItem('maggot_sidebar_hover') === 'true';
        }
    }"
    @mouseenter="if (hoverMode) isHovered = true"
    @mouseleave="if (hoverMode) isHovered = false"
    :class="(hoverMode && !isHovered) ? 'w-[76px]' : 'w-[300px]'"
    class="bg-(--bg2-colour) border-r-[1.5px] border-(--outline-colour) flex flex-col justify-between shrink-0 h-screen transition-all duration-300 ease-in-out select-none relative z-30 overflow-hidden"
>
    <!-- Bagian Atas: Logo (26px, tanpa padding atas-bawah berlebih) & Toggle Hover Mode & Menu Navigasi -->
    <div class="flex flex-col flex-1 min-h-0">
        <!-- Area Logo (26px) & Tombol Toggle Mode Hover di sampingnya -->
        <div
            class="flex items-center px-4 py-4 border-b border-(--outline-colour)/40"
            :class="(hoverMode && !isHovered) ? 'justify-center' : 'justify-between'"
        >
            <!-- Logo 26px & Nama Aplikasi -->
            <div class="flex items-center gap-2.5 overflow-hidden">
                <div class="w-[26px] h-[26px] bg-[#163428] rounded-full flex items-center justify-center text-white font-bold text-[11px] shadow-sm shrink-0">
                    M
                </div>
                <span
                    x-show="!hoverMode || isHovered"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-x--2"
                    x-transition:enter-end="opacity-100 translate-x-0"
                    class="font-extrabold text-[#163428] text-base tracking-wider whitespace-nowrap"
                >
                    MAGGOT
                </span>
            </div>

            <!-- Tombol Toggle Mode Hover di Samping Logo -->
            <button
                type="button"
                @click="toggleHoverMode()"
                :title="hoverMode ? 'Mode Hover: Aktif (Klik untuk Kunci Lebar Penuh)' : 'Mode Hover: Nonaktif (Klik untuk Mode Otomatis Mengecil)'"
                class="p-1.5 rounded-xl text-gray-500 hover:text-[#163428] hover:bg-gray-300/60 transition cursor-pointer shrink-0"
                x-show="!hoverMode || isHovered"
            >
                <template x-if="hoverMode">
                    <x-lucide-panel-left-close class="w-4 h-4 text-emerald-700"/>
                </template>
                <template x-if="!hoverMode">
                    <x-lucide-panel-left class="w-4 h-4"/>
                </template>
            </button>
        </div>

        <!-- Tombol Toggle khusus saat dalam mode collapsed (jika diperlukan) -->

        <!-- Navigation Links Menu -->
        <nav class="space-y-(--size-10) px-3 py-4 flex-1 overflow-y-auto overflow-x-hidden">
            @php
                $navItems = [
                    [
                        'route' => 'dashboard.index',
                        'label' => 'Dashboard',
                        'icon'  => 'lucide-layout-dashboard',
                    ],
                    [
                        'route' => 'observation.index',
                        'label' => 'Catatan Observasi',
                        'icon'  => 'lucide-notebook-text',
                    ],
                    [
                        'route' => 'reports.index',
                        'label' => 'Laporan dan Analisis',
                        'icon'  => 'lucide-chart-column',
                    ],
                    [
                        'route' => 'settings.index',
                        'label' => 'Pengaturan Perangkat',
                        'icon'  => 'lucide-bolt',
                    ],
                    [
                        'route' => 'account.index',
                        'label' => 'Manajemen Akun',
                        'icon'  => 'lucide-square-user-round',
                    ],
                ];
            @endphp

            @foreach($navItems as $item)
                @php
                    $isActive = request()->routeIs($item['route'] . '*');
                @endphp
                <div class="w-full rounded-(--size-16) border-[1.5px] transition-all {{ $isActive ? 'text-(--fg-colour) bg-(--prime-colour) border-transparent shadow-xs font-semibold' : 'text-(--text-colour) hover:bg-(--bg-colour) border-transparent hover:border-(--outline-colour)' }}">
                    <a
                        href="{{ route($item['route']) }}"
                        title="{{ $item['label'] }}"
                        class="flex items-center gap-(--size-10) py-(--size-10) font-medium text-sm overflow-hidden whitespace-nowrap transition-all"
                        :class="(hoverMode && !isHovered) ? 'justify-center px-0' : 'px-3.5'"
                    >
                        <x-dynamic-component :component="$item['icon']" class="w-5 h-5 shrink-0" />

                        <span
                            x-show="!hoverMode || isHovered"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            class="truncate"
                        >
                            {{ $item['label'] }}
                        </span>
                    </a>
                </div>
            @endforeach
        </nav>
    </div>

    <!-- Bagian Bawah: Profil User (Avatar, Full Name, Username) & Tombol Logout di Sampingnya -->
    <div class="p-3 border-t border-(--outline-colour) bg-(--bg2-colour)/90">
        @auth
            <div
                class="flex items-center gap-2.5"
                :class="(hoverMode && !isHovered) ? 'justify-center' : 'justify-between'"
            >
                <!-- Icon Profile (Avatar) -->
                <div
                    class="w-9 h-9 rounded-full bg-emerald-100 text-[#163428] flex items-center justify-center font-bold text-sm shrink-0 border border-emerald-300 shadow-xs"
                    title="{{ Auth::user()?->full_name ?? Auth::user()?->username }}"
                >
                    <x-lucide-user class="w-4 h-4 text-[#163428]"/>
                </div>

                <!-- Di sampingnya: full_name dan di bawahnya username -->
                <div
                    x-show="!hoverMode || isHovered"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="flex-1 min-w-0 flex flex-col justify-center overflow-hidden"
                >
                    <span class="text-xs font-bold text-gray-900 truncate leading-tight">
                        {{ Auth::user()?->full_name ?? Auth::user()?->username ?? 'Petugas' }}
                    </span>
                    <span class="text-[11px] text-gray-500 truncate leading-tight mt-0.5">
                        {{ '@' . (Auth::user()?->username ?? 'user') }}
                    </span>
                </div>

                <!-- Di sampingnya: Tombol Logout -->
                <div
                    x-show="!hoverMode || isHovered"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="shrink-0"
                >
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button
                            type="submit"
                            title="Keluar dari Aplikasi"
                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition cursor-pointer flex items-center justify-center"
                        >
                            <x-lucide-log-out class="w-4 h-4"/>
                        </button>
                    </form>
                </div>
            </div>
        @else
            <div class="flex items-center" :class="(hoverMode && !isHovered) ? 'justify-center' : 'justify-between'">
                <a href="{{ route('login') }}" class="flex items-center gap-2 p-2 rounded-xl text-xs font-bold text-[#163428] hover:bg-emerald-50 transition w-full" title="Login">
                    <x-lucide-log-in class="w-4 h-4 shrink-0"/>
                    <span x-show="!hoverMode || isHovered">Masuk</span>
                </a>
            </div>
        @endauth
    </div>
</aside>
