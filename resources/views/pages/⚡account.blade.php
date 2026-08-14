<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;

new class extends Component
{
    use WithPagination;

    // Field Profil Saya (Pre-input dari Auth::user())
    public string $fullName = '';
    public string $userName = '';

    // Field Ubah Password
    public string $newPassword = '';
    public string $confirmPassword = '';

    // Flash Messages
    public string $profileMessage = '';
    public string $passwordMessage = '';

    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            $this->fullName = $user->full_name ?? '';
            $this->userName = $user->username ?? '';
        }
    }

    public function changeProfile()
    {
        $user = Auth::user();
        if (!$user) return;

        $this->validate([
            'fullName' => 'required|string|max:255',
            'userName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
        ], [
            'fullName.required' => 'Nama lengkap wajib diisi.',
            'userName.required' => 'Username wajib diisi.',
            'userName.unique'   => 'Username ini sudah digunakan.',
        ]);

        $user->update([
            'full_name' => $this->fullName,
            'username'  => $this->userName,
        ]);

        $this->profileMessage = 'Profil berhasil diperbarui.';
    }

    public function changePassword()
    {
        $user = Auth::user();
        if (!$user) return;

        $this->validate([
            'newPassword'     => 'required|string|min:6',
            'confirmPassword' => 'required|string|same:newPassword',
        ], [
            'newPassword.required'     => 'Password baru wajib diisi.',
            'newPassword.min'          => 'Password minimal 6 karakter.',
            'confirmPassword.required' => 'Konfirmasi password wajib diisi.',
            'confirmPassword.same'     => 'Konfirmasi password tidak cocok dengan password baru.',
        ]);

        $user->update([
            'password_hash' => Hash::make($this->newPassword),
        ]);

        $this->reset(['newPassword', 'confirmPassword']);
        $this->passwordMessage = 'Password berhasil diubah.';
    }

    public function with(): array {
        $userData = [];

        if (Gate::allows('manage-accounts')) {
            $userData = User::orderBy('full_name', 'asc')->paginate(10);
        }

        return [
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
        <!-- Box Profil Saya -->
        <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) w-full">
            <div class="flex flex-row gap-(--size-16) items-center">
                <x-lucide-user-round class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16)"/>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Profil Saya</span>
            </div>

            @if ($profileMessage)
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-init="setTimeout(() => show = false, 4000)"
                    class="flex items-center gap-2 p-3 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl text-xs font-semibold"
                >
                    <x-lucide-check-circle class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ $profileMessage }}</span>
                </div>
            @endif

            <form wire:submit="changeProfile" id="changeProfileForm" class="flex flex-col gap-(--size-16) min-w-(--size-492) w-full">
                <div class="flex flex-row gap-(--size-16)">
                    <div class="input-container w-full">
                        <label for="fullName">Nama Lengkap</label>
                        <input
                            wire:model="fullName"
                            id="fullName"
                            type="text"
                            placeholder="Masukkan Nama Lengkap"
                            class="input-text @error('fullName') border-red-500 @enderror"
                        />
                        @error('fullName')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="input-container w-full">
                        <label for="userName">Username</label>
                        <input
                            wire:model="userName"
                            id="userName"
                            type="text"
                            placeholder="Masukkan Username"
                            class="input-text @error('userName') border-red-500 @enderror"
                        />
                        @error('userName')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full input-button cursor-pointer flex items-center justify-center gap-2 hover:opacity-90">
                    <x-lucide-user-round class="w-(--size-26)"/>
                    <span wire:loading.remove wire:target="changeProfile">Ubah Profil</span>
                    <span wire:loading wire:target="changeProfile">Menyimpan...</span>
                </button>
            </form>
        </div>

        <!-- Box Ubah Password -->
        <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) min-w-(--size-492) w-full">
            <div class="flex flex-row gap-(--size-16) items-center">
                <x-lucide-square-asterisk class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16)"/>
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Ubah Password</span>
            </div>

            @if ($passwordMessage)
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-init="setTimeout(() => show = false, 4000)"
                    class="flex items-center gap-2 p-3 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl text-xs font-semibold"
                >
                    <x-lucide-check-circle class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ $passwordMessage }}</span>
                </div>
            @endif

            <form wire:submit="changePassword" id="changePasswordForm" class="flex flex-col gap-(--size-16)">
                <div class="flex flex-row gap-(--size-16)">
                    <div class="input-container w-full">
                        <label for="newPassword">Password Baru</label>
                        <input
                            wire:model="newPassword"
                            id="newPassword"
                            type="password"
                            placeholder="Masukkan Password Baru"
                            class="input-text @error('newPassword') border-red-500 @enderror"
                        />
                        @error('newPassword')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="input-container w-full">
                        <label for="confirmPassword">Konfirmasi Password</label>
                        <input
                            wire:model="confirmPassword"
                            id="confirmPassword"
                            type="password"
                            placeholder="Konfirmasi Password Baru"
                            class="input-text @error('confirmPassword') border-red-500 @enderror"
                        />
                        @error('confirmPassword')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full input-button cursor-pointer flex items-center justify-center gap-2 hover:opacity-90">
                    <x-lucide-pen-line class="w-(--size-26)"/>
                    <span wire:loading.remove wire:target="changePassword">Ubah Password</span>
                    <span wire:loading wire:target="changePassword">Menyimpan...</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Bagian Daftar Pengguna (Khusus Admin) -->
    @can('manage-accounts')
        <div class="flex flex-row justify-between items-center pt-2">
            <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Daftar Pengguna</span>
            <button class="input-button cursor-pointer flex items-center gap-2">
                <x-lucide-user-round-plus class="w-(--size-26)"/>
                <span>Tambah Pengguna</span>
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
                        <td class="w-min">
                            <span class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $item->role === 'admin' ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-800' }}">
                                {{ ($item->role === 'admin') ? 'Admin' : 'Pengguna' }}
                            </span>
                        </td>
                        <td class="border-r-0 flex flex-row gap-(--size-10) w-full justify-center py-2">
                            <button class="input-button p-(--size-10) cursor-pointer">
                                <x-lucide-square-pen class="w-(--size-16)"/>
                            </button>
                            <button class="input-button p-(--size-10) bg-red-600 hover:bg-red-700 cursor-pointer">
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
