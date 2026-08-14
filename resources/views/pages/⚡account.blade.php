<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

new class extends Component
{
    public function with(): array {
        // Siapkan variabel kosong terlebih dahulu
        $userData = [];

        // GATE BERAKSI: Cek diam-diam, apakah dia berhak mengelola akun?
        // Jika YA (Admin), barulah kita ambilkan data dari database
        if (Gate::allows('manage-accounts')) {
            $userData = User::orderBy('full_name', 'asc')->paginate(10);
        }

        return [
            // Kirim ke Blade
            'users' => $userData,
        ];
    }
};
?>

<div class="space-y-(--size-26)">
    <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold">
        Manajemen Akun
    </h1>
    <div class="flex flex-row gap-(--size-26)">
        <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16)">
            <div class="flex flex-row gap-(--size-16) items-center">
                <x-lucide-user-round class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16)"/>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Profil Saya</span>
            </div>
            <form wire:submit="changeProfile" id="changeProfileForm" class="flex flex-col gap-(--size-16)">
                <div class="flex flex-row gap-(--size-16)">
                    <div class="input-container w-full">
                        <label for="fullName">Nama Lengkap</label>
                        <input
                            wire:model="fullName"
                            id="fullName"
                            type="text"
                            placeholder="Masukkan Nama Lengkap"
                            class="input-text"
                        />
                    </div>
                    <div class="input-container w-full">
                        <label for="userName">Username</label>
                        <input
                            wire:model="userName"
                            id="userName"
                            type="text"
                            placeholder="Masukkan Username"
                            class="input-text"
                        />
                    </div>
                </div>
                <button type="submit" class="w-full input-button">
                    <x-lucide-user-round class="w-(--size-26)"/>
                    Ubah Profil
                </button>
            </form>
        </div>
        <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16)">
            <div class="flex flex-row gap-(--size-16) items-center">
                <x-lucide-square-asterisk class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16)"/>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Ubah Password</span>
            </div>
            <form wire:submit="changePassword" id="changePasswordForm" class="flex flex-col gap-(--size-16)">
                <div class="flex flex-row gap-(--size-16)">
                    <div class="input-container w-full">
                        <label for="newPassword">Password Baru</label>
                        <input
                            wire:model="newPassword"
                            id="newPassword"
                            type="text"
                            placeholder="Masukkan Password Baru"
                            class="input-text"
                        />
                    </div>
                    <div class="input-container w-full">
                        <label for="confirmPassword">Konfirmasi Password</label>
                        <input
                            wire:model="confirmPassword"
                            id="confirmPassword"
                            type="text"
                            placeholder="Konfirmasi Password Baru"
                            class="input-text"
                        />
                    </div>
                </div>
                <button type="submit" class="w-full input-button">
                    <x-lucide-pen-line class="w-(--size-26)"/>
                    Ubah Password
                </button>
            </form>
        </div>
    </div>
    @can('manage-accounts')
        <div class="flex flex-row justify-between items-center">
            <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Daftar Pengguna</span>
            <button class="input-button">
                <x-lucide-user-round-plus class="w-(--size-26)"/>
                Tambah Pengguna
            </button>
        </div>
        <div class="overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) min-w-max w-full">
            <table class="w-full text-left border-collapse">

                <thead class="border-b-[1.5px] border-(--prime-light-colour) bg-(--prime-colour)">
                    <tr>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th class="w-[120px]">Peran</th>
                        <th class="border-r-0 w-[132px]">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($users as $item)
                    <tr class="border-b-[1.5px] border-(--outline-colour) hover:bg-gray-50 transition-colors">
                        <td>{{ $item->full_name }}</td>
                        <td>{{ $item->username}}</td>
                        <td class="w-min">{{ ($item->role === 'admin') ? 'Admin' : 'Pengguna' }}</td>
                        <td class="border-r-0 flex flex-row gap-(--size-10) w-full justify-center">
                            <button class="input-button p-(--size-10)">
                                <x-lucide-square-pen class="w-(--size-16)"/>
                            </button>
                            <button class="input-button p-(--size-10) bg-red-300 text-red-600">
                                <x-lucide-trash-2 class="w-(--size-16)"/>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="m-0">
            {{ $users->links() }}
        </div>
    @endcan
</div>
