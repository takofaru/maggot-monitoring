<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.guest')] class extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;
    public string $errorMessage = '';
    public int $errorCount = 0;

    public function mount()
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard.index'));
        }
    }

    public function login()
    {
        $this->errorMessage = '';

        $this->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if (Auth::attempt(['username' => $this->username, 'password' => $this->password], $this->remember)) {
            session()->regenerate();
            return redirect()->intended(route('dashboard.index'));
        }

        $this->errorMessage = 'Username atau password yang Anda masukkan salah.';
        $this->errorCount++;
    }
};
?>

<div class="relative w-screen h-screen flex justify-center items-center bg-(--bg-colour) overflow-hidden">
    <!-- Notifikasi Error dari Atas (Otomatis Hilang 5 Detik) -->
    @if ($errorMessage)
        <div
            wire:key="toast-{{ $errorCount }}"
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="-translate-y-8 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-300 transform"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="-translate-y-8 opacity-0"
            class="fixed top-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-5 py-3.5 bg-red-600 text-white rounded-2xl shadow-xl border border-red-700 max-w-sm w-full mx-4"
        >
            <x-lucide-alert-circle class="w-5 h-5 shrink-0" />
            <span class="text-xs md:text-sm font-medium flex-1">{{ $errorMessage }}</span>
            <button type="button" @click="show = false" class="text-white/80 hover:text-white cursor-pointer transition-colors">
                <x-lucide-x class="w-4 h-4" />
            </button>
        </div>
    @endif

    <div class="flex flex-col gap-(--size-42) min-w-(--size-304) max-w-(--size-304) items-center">
        <div id="logo">
            <div class="w-16 h-16 bg-[#163428] text-white rounded-full flex items-center justify-center font-bold text-sm shadow-md">
                MAGGOT
            </div>
        </div>

        <div id="login" class="flex flex-col gap-(--size-26) w-full">

            <div class="text-(length:--size-42) font-[700] text-(--prime-colour) text-center">Masuk ke Akun</div>

            <div id="login-container" class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) rounded-(--size-16) border-[1.5px] border-(--outline-colour)">
                <form wire:submit="login" id="loginForm" class="flex flex-col gap-(--size-16)">
                    <div class="input-container">
                        <div class="flex items-center gap-1.5">
                            <label for="username">Username</label>
                            @error('username')
                                <div class="relative inline-flex items-center group cursor-pointer">
                                    <x-lucide-alert-circle class="w-(--size-16) text-red-500 hover:text-red-600 transition-colors" />
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:flex flex-col items-center z-30 pointer-events-none">
                                        <div class="bg-red-600 text-white text-xs font-medium px-2.5 py-1 rounded-lg shadow-lg whitespace-nowrap">
                                            {{ $message }}
                                        </div>
                                        <div class="w-2 h-1 bg-red-600 [clip-path:polygon(0_0,100%_0,50%_100%)]"></div>
                                    </div>
                                </div>
                            @enderror
                        </div>
                        <input
                            wire:model="username"
                            id="username"
                            type="text"
                            class="input-text @error('username') border-red-500 @enderror {{ $errorMessage ? 'border-red-500' : '' }}"
                            placeholder="Masukkan Username"
                            autocomplete="username"
                            autofocus
                        />
                    </div>

                    <div class="input-container" x-data="{ showPass: false }">
                        <div class="flex items-center gap-1.5">
                            <label for="password">Password</label>
                            @error('password')
                                <div class="relative inline-flex items-center group cursor-pointer">
                                    <x-lucide-alert-circle class="w-(--size-16) text-red-500 hover:text-red-600 transition-colors" />
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:flex flex-col items-center z-30 pointer-events-none">
                                        <div class="bg-red-600 text-white text-xs font-medium px-2.5 py-1 rounded-lg shadow-lg whitespace-nowrap">
                                            {{ $message }}
                                        </div>
                                        <div class="w-2 h-1 bg-red-600 [clip-path:polygon(0_0,100%_0,50%_100%)]"></div>
                                    </div>
                                </div>
                            @enderror
                        </div>
                        <div class="flex flex-row items-center justify-between input-text @error('password') border-red-500 @enderror {{ $errorMessage ? 'border-red-500' : '' }}">
                            <input
                                wire:model="password"
                                id="password"
                                :type="showPass ? 'text' : 'password'"
                                placeholder="Masukkan Password"
                                autocomplete="current-password"
                                class="w-full bg-transparent focus:outline-none"
                            />
                            <button type="button" @click="showPass = !showPass" class="cursor-pointer text-gray-500 hover:text-gray-700">
                                <x-lucide-eye x-show="!showPass" class="w-(--size-16)"/>
                                <x-lucide-eye-off x-show="showPass" x-cloak class="w-(--size-16)"/>
                            </button>
                        </div>
                    </div>

                    <button class="input-button cursor-pointer flex items-center justify-center gap-2" type="submit" wire:loading.attr="disabled">
                        <x-lucide-log-in class="w-(--size-16)" />
                        <span wire:loading.remove wire:target="login">Masuk ke Akun</span>
                        <span wire:loading wire:target="login">Memproses...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
