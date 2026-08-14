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
    }
};
?>

<div class="w-screen h-screen flex justify-center items-center bg-(--bg-colour)">
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

                    <!-- Field Username -->
                    <div class="input-container">
                        <label for="username">Username</label>
                        <input
                            wire:model="username"
                            id="username"
                            type="text"
                            class="input-text @error('username') border-red-500 @enderror {{ $errorMessage ? 'border-red-500' : '' }}"
                            placeholder="Masukkan Username"
                            autocomplete="username"
                            autofocus
                        />
                        @error('username')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Field Password -->
                    <div class="input-container" x-data="{ showPass: false }">
                        <label for="password">Password</label>
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
                        @error('password')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Box Notifikasi Login Gagal (Di Atas Tombol Login) -->
                    @if ($errorMessage)
                        <div class="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-(--size-16) text-red-700 text-xs font-medium">
                            <x-lucide-alert-circle class="w-4 h-4 text-red-500 shrink-0" />
                            <span>{{ $errorMessage }}</span>
                        </div>
                    @endif

                    <button class="input-button cursor-pointer flex items-center justify-center gap-2" type="submit" wire:loading.attr="disabled">
                        <x-lucide-log-in class="w-(--size-26)" />
                        <span wire:loading.remove wire:target="login">Masuk ke Akun</span>
                        <span wire:loading wire:target="login">Memproses...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
