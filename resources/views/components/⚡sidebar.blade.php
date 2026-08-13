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

<aside class="bg-(--bg2-colour) border-r-[1.5px] border-(--outline-colour) flex flex-col justify-between shrink-0 max-h-screen">
    <div class="space-y-(--size-26) px-(--size-26) py-(--size-42)">
        <!-- Logo -->
        <div class="flex items-center py-(--size-26)">
            <div class="w-20 h-20 bg-[#163428] rounded-full flex items-center justify-center text-white font-bold text-sm shadow-md">
                MAGGOT
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="space-y-(--size-16)">
            <livewire:nav-link route="dashboard.index" icon="layout-dashboard">
                Dashboard
            </livewire:nav-link>

            <livewire:nav-link route="maintenance.index" icon="notebook-text">
                Catatan Pemeliharaan
            </livewire:nav-link>

            <livewire:nav-link route="reports.index" icon="chart-column">
                Laporan dan Analisis
            </livewire:nav-link>

            <livewire:nav-link route="settings.index" icon="bolt">
                Pengaturan Perangkat
            </livewire:nav-link>

            <livewire:nav-link route="account.index" icon="square-user-round">
                {{ $isAdmin ? 'Manajemen Akun' : 'Akun Saya' }}
            </livewire:nav-link>
        </nav>
    </div>

    <!-- Tombol Logout / Login -->
    <div class="pt-6 border-t border-gray-200">
        @auth
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl font-bold text-sm text-red-600 hover:bg-red-50 transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span>Keluar</span>
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-bold text-sm text-[#163428] hover:bg-emerald-50 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                <span>Login</span>
            </a>
        @endauth
    </div>
</aside>
