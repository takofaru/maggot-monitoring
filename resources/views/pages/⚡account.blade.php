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

    // State Modal User (Khusus Admin)
    public bool $openUserModal = false;
    public ?int $editingUserId = null;
    public string $userFullName = '';
    public string $userUsername = '';
    public string $userRole = 'user';
    public string $userPassword = '';
    public string $userConfirmPassword = '';

    // Flash Messages
    public string $profileMessage = '';
    public string $passwordMessage = '';
    public string $userMessage = '';

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

    // --- Manajemen Pengguna (Admin Only) ---

    public function openCreateUserModal()
    {
        if (!Gate::allows('manage-accounts')) return;

        $this->resetUserForm();
        $this->editingUserId = null;
        $this->openUserModal = true;
    }

    public function openEditUserModal($id)
    {
        if (!Gate::allows('manage-accounts')) return;

        $user = User::findOrFail($id);
        $this->editingUserId = $user->id;
        $this->userFullName = $user->full_name;
        $this->userUsername = $user->username;
        $this->userRole = $user->role;
        $this->userPassword = '';
        $this->userConfirmPassword = '';
        $this->openUserModal = true;
    }

    public function closeUserModal()
    {
        $this->resetUserForm();
        $this->openUserModal = false;
    }

    public function resetUserForm()
    {
        $this->reset(['userFullName', 'userUsername', 'userRole', 'userPassword', 'userConfirmPassword', 'editingUserId']);
        $this->resetErrorBag();
    }

    public function saveUser()
    {
        if (!Gate::allows('manage-accounts')) return;

        $rules = [
            'userFullName' => 'required|string|max:255',
            'userUsername' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($this->editingUserId),
            ],
            'userRole' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
        ];

        if ($this->editingUserId) {
            // Mode Edit: password bersifat opsional
            if (!empty($this->userPassword)) {
                $rules['userPassword'] = 'required|string|min:6';
                $rules['userConfirmPassword'] = 'required|string|same:userPassword';
            }
        } else {
            // Mode Tambah: password wajib diisi
            $rules['userPassword'] = 'required|string|min:6';
            $rules['userConfirmPassword'] = 'required|string|same:userPassword';
        }

        $this->validate($rules, [
            'userFullName.required'        => 'Nama lengkap wajib diisi.',
            'userUsername.required'        => 'Username wajib diisi.',
            'userUsername.unique'          => 'Username sudah terdaftar.',
            'userRole.required'            => 'Peran wajib dipilih.',
            'userPassword.required'        => 'Password wajib diisi.',
            'userPassword.min'             => 'Password minimal 6 karakter.',
            'userConfirmPassword.required' => 'Konfirmasi password wajib diisi.',
            'userConfirmPassword.same'     => 'Konfirmasi password tidak cocok.',
        ]);

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);
            $data = [
                'full_name' => $this->userFullName,
                'username'  => $this->userUsername,
                'role'      => $this->userRole,
            ];
            if (!empty($this->userPassword)) {
                $data['password_hash'] = Hash::make($this->userPassword);
            }
            $user->update($data);
            $this->userMessage = "Data pengguna {$user->username} berhasil diperbarui.";
        } else {
            User::create([
                'full_name'     => $this->userFullName,
                'username'      => $this->userUsername,
                'role'          => $this->userRole,
                'password_hash' => Hash::make($this->userPassword),
            ]);
            $this->userMessage = "Pengguna baru {$this->userUsername} berhasil ditambahkan.";
        }

        $this->closeUserModal();
        $this->resetPage();
    }

    public function deleteUser($id)
    {
        if (!Gate::allows('manage-accounts')) return;

        if ($id == Auth::id()) {
            $this->userMessage = 'Anda tidak dapat menghapus akun Anda sendiri.';
            return;
        }

        $user = User::find($id);
        if ($user) {
            $username = $user->username;
            $user->delete();
            $this->userMessage = "Pengguna {$username} berhasil dihapus.";
        }
    }

    public function with(): array {
        $userData = [];

        if (Gate::allows('manage-accounts')) {
            $userData = User::orderBy('id', 'asc')->paginate(10);
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-(--size-26) w-full">
        <!-- Box Profil Saya -->
        <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) w-full min-w-0 shadow-xs">
            <div class="flex flex-row gap-(--size-16) items-center">
                <x-lucide-user-round class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
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

            <form wire:submit="changeProfile" id="changeProfileForm" class="flex flex-col gap-(--size-16) w-full min-w-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-(--size-16) w-full">
                    <div class="input-container w-full min-w-0">
                        <label for="fullName">Nama Lengkap</label>
                        <input
                            wire:model="fullName"
                            id="fullName"
                            type="text"
                            placeholder="Masukkan Nama Lengkap"
                            class="input-text @error('fullName') border-red-500 @enderror w-full min-w-0"
                        />
                        @error('fullName')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="input-container w-full min-w-0">
                        <label for="userName">Username</label>
                        <input
                            wire:model="userName"
                            id="userName"
                            type="text"
                            placeholder="Masukkan Username"
                            class="input-text @error('userName') border-red-500 @enderror w-full min-w-0"
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
        <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) w-full min-w-0 shadow-xs">
            <div class="flex flex-row gap-(--size-16) items-center">
                <x-lucide-square-asterisk class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16) shrink-0"/>
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

            <form wire:submit="changePassword" id="changePasswordForm" class="flex flex-col gap-(--size-16) w-full min-w-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-(--size-16) w-full">
                    <!-- Password Baru dengan Toggle Show/Hide -->
                    <div class="input-container w-full min-w-0" x-data="{ showPass: false }">
                        <label for="newPassword">Password Baru</label>
                        <div class="flex flex-row items-center justify-between input-text @error('newPassword') border-red-500 @enderror">
                            <input
                                wire:model="newPassword"
                                id="newPassword"
                                :type="showPass ? 'text' : 'password'"
                                placeholder="Masukkan Password Baru"
                                class="w-full bg-transparent focus:outline-none min-w-0"
                            />
                            <button type="button" @click="showPass = !showPass" class="cursor-pointer text-gray-500 hover:text-gray-700 shrink-0 ml-2">
                                <x-lucide-eye x-show="!showPass" class="w-(--size-16)"/>
                                <x-lucide-eye-off x-show="showPass" x-cloak class="w-(--size-16)"/>
                            </button>
                        </div>
                        @error('newPassword')
                            <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Konfirmasi Password dengan Toggle Show/Hide -->
                    <div class="input-container w-full min-w-0" x-data="{ showPass: false }">
                        <label for="confirmPassword">Konfirmasi Password</label>
                        <div class="flex flex-row items-center justify-between input-text @error('confirmPassword') border-red-500 @enderror">
                            <input
                                wire:model="confirmPassword"
                                id="confirmPassword"
                                :type="showPass ? 'text' : 'password'"
                                placeholder="Konfirmasi Password Baru"
                                class="w-full bg-transparent focus:outline-none min-w-0"
                            />
                            <button type="button" @click="showPass = !showPass" class="cursor-pointer text-gray-500 hover:text-gray-700 shrink-0 ml-2">
                                <x-lucide-eye x-show="!showPass" class="w-(--size-16)"/>
                                <x-lucide-eye-off x-show="showPass" x-cloak class="w-(--size-16)"/>
                            </button>
                        </div>
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
            <div class="flex items-center gap-3">
                <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Daftar Pengguna</span>
                @if ($userMessage)
                    <div
                        x-data="{ show: true }"
                        x-show="show"
                        x-init="setTimeout(() => show = false, 4000)"
                        class="flex items-center gap-1.5 px-3 py-1 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-lg text-xs font-semibold"
                    >
                        <x-lucide-check-circle class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                        <span>{{ $userMessage }}</span>
                    </div>
                @endif
            </div>
            <button
                wire:click="openCreateUserModal"
                type="button"
                class="input-button cursor-pointer flex items-center gap-2 hover:opacity-90"
            >
                <x-lucide-user-round-plus class="w-(--size-26)"/>
                <span>Tambah Pengguna</span>
            </button>
        </div>

        <div class="overflow-hidden border-[1.5px] border-(--prime-light-colour) rounded-(length:--size-16) min-w-max w-full shadow-xs">
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
                    @forelse($users as $item)
                    <tr class="border-b-[1.5px] border-(--outline-colour) hover:bg-gray-50 transition-colors">
                        <td>
                            <div class="font-medium text-gray-900 flex items-center gap-2">
                                <span>{{ $item->full_name }}</span>
                                @if($item->id === Auth::id())
                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-[10px] font-bold">Anda</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $item->username }}</td>
                        <td class="w-min">
                            <span class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $item->role === 'admin' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-gray-100 text-gray-800' }}">
                                {{ ($item->role === 'admin') ? 'Admin' : 'Pengguna' }}
                            </span>
                        </td>
                        <td class="border-r-0 flex flex-row gap-(--size-10) w-full justify-center py-2">
                            <button
                                wire:click="openEditUserModal({{ $item->id }})"
                                type="button"
                                title="Ubah Pengguna"
                                class="input-button p-(--size-10) cursor-pointer hover:bg-(--prime-light-colour)"
                            >
                                <x-lucide-square-pen class="w-(--size-16)"/>
                            </button>
                            @if($item->id !== Auth::id())
                                <button
                                    wire:click="deleteUser({{ $item->id }})"
                                    wire:confirm="Yakin ingin menghapus pengguna {{ $item->username }}?"
                                    type="button"
                                    title="Hapus Pengguna"
                                    class="input-button p-(--size-10) bg-red-600 hover:bg-red-700 cursor-pointer"
                                >
                                    <x-lucide-trash-2 class="w-(--size-16)"/>
                                </button>
                            @else
                                <button
                                    type="button"
                                    disabled
                                    title="Akun Anda saat ini (tidak dapat dihapus)"
                                    class="input-button p-(--size-10) opacity-30 cursor-not-allowed grayscale bg-red-600 pointer-events-none"
                                >
                                    <x-lucide-trash-2 class="w-(--size-16)"/>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-8 text-gray-400">
                            Tidak ada pengguna ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="m-0">
            {{ $users->links() }}
        </div>

        <!-- Modal Tambah / Ubah Pengguna (Khusus Admin) -->
        @if ($openUserModal)
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4"
                x-transition.opacity
            >
                <div
                    @click.outside="$wire.closeUserModal()"
                    class="w-full max-w-(--size-492) bg-(--fg-colour) rounded-(--size-16) px-(--size-26) py-(--size-42) border-[1.5px] border-(--outline-colour) shadow-2xl space-y-(--size-26) max-h-[90vh] overflow-y-auto"
                >
                    <form wire:submit="saveUser" class="flex flex-col gap-(--size-26) w-full">
                        <!-- Header Modal -->
                        <div class="flex items-center justify-between">
                            <span class="text-(length:--size-26) text-(--prime-colour) font-bold">
                                {{ $editingUserId ? 'Ubah Data Pengguna' : 'Tambah Pengguna Baru' }}
                            </span>
                            <button
                                type="button"
                                wire:click="closeUserModal"
                                class="text-gray-400 hover:text-gray-600 cursor-pointer text-xl font-bold"
                            >
                                &times;
                            </button>
                        </div>

                        <!-- 1. Baris 1: Nama Lengkap dan Username -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-(--size-16) w-full">
                            <div class="input-container w-full min-w-0">
                                <label for="userFullName">Nama Lengkap</label>
                                <input
                                    wire:model="userFullName"
                                    id="userFullName"
                                    type="text"
                                    placeholder="Contoh: Ahmad Fadli"
                                    class="input-text @error('userFullName') border-red-500 @enderror w-full min-w-0"
                                />
                                @error('userFullName')
                                    <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="input-container w-full min-w-0">
                                <label for="userUsername">Username</label>
                                <input
                                    wire:model="userUsername"
                                    id="userUsername"
                                    type="text"
                                    placeholder="Contoh: ahmad"
                                    class="input-text @error('userUsername') border-red-500 @enderror w-full min-w-0"
                                />
                                @error('userUsername')
                                    <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- 2. Baris 2: Password dan Konfirmasi Password (dengan Toggle Lihat Password) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-(--size-16) w-full">
                            <div class="input-container w-full min-w-0" x-data="{ showPass: false }">
                                <label for="userPassword">
                                    {{ $editingUserId ? 'Password Baru (Opsional)' : 'Password' }}
                                </label>
                                <div class="flex flex-row items-center justify-between input-text @error('userPassword') border-red-500 @enderror">
                                    <input
                                        wire:model="userPassword"
                                        id="userPassword"
                                        :type="showPass ? 'text' : 'password'"
                                        placeholder="{{ $editingUserId ? 'Kosongkan jika sama' : 'Minimal 6 karakter' }}"
                                        class="w-full bg-transparent focus:outline-none min-w-0"
                                    />
                                    <button type="button" @click="showPass = !showPass" class="cursor-pointer text-gray-500 hover:text-gray-700 shrink-0 ml-2">
                                        <x-lucide-eye x-show="!showPass" class="w-(--size-16)"/>
                                        <x-lucide-eye-off x-show="showPass" x-cloak class="w-(--size-16)"/>
                                    </button>
                                </div>
                                @error('userPassword')
                                    <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="input-container w-full min-w-0" x-data="{ showPass: false }">
                                <label for="userConfirmPassword">Konfirmasi Password</label>
                                <div class="flex flex-row items-center justify-between input-text @error('userConfirmPassword') border-red-500 @enderror">
                                    <input
                                        wire:model="userConfirmPassword"
                                        id="userConfirmPassword"
                                        :type="showPass ? 'text' : 'password'"
                                        placeholder="Ulangi password"
                                        class="w-full bg-transparent focus:outline-none min-w-0"
                                    />
                                    <button type="button" @click="showPass = !showPass" class="cursor-pointer text-gray-500 hover:text-gray-700 shrink-0 ml-2">
                                        <x-lucide-eye x-show="!showPass" class="w-(--size-16)"/>
                                        <x-lucide-eye-off x-show="showPass" x-cloak class="w-(--size-16)"/>
                                    </button>
                                </div>
                                @error('userConfirmPassword')
                                    <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- 3. Baris 3: Peran (Role) dengan Custom Dropdown Alpine -->
                        <div class="input-container w-full min-w-0" x-data="{ openRoleDropdown: false }">
                            <label>Peran</label>
                            <div class="relative w-full">
                                <button
                                    @click="openRoleDropdown = !openRoleDropdown"
                                    type="button"
                                    class="w-full rounded-(--size-16) inline-flex justify-between items-center gap-(--size-10) input-text text-(--size-16) hover:bg-(--bg2-colour) cursor-pointer"
                                >
                                    <span>{{ $userRole === 'admin' ? 'Administrator' : 'Pengguna (Siswa / Operator)' }}</span>
                                    <x-lucide-chevron-down class="w-(--size-16) shrink-0"/>
                                </button>

                                <div
                                    x-show="openRoleDropdown"
                                    @click.outside="openRoleDropdown = false"
                                    x-transition.opacity.duration.200ms
                                    class="absolute left-0 top-full mt-(--size-10) w-full bg-white border border-gray-300 rounded-(--size-16) shadow-xl z-50 overflow-hidden"
                                    x-cloak
                                >
                                    <button
                                        type="button"
                                        wire:click="$set('userRole', 'user')"
                                        @click="openRoleDropdown = false"
                                        class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 border-b border-gray-100 cursor-pointer {{ $userRole === 'user' ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
                                    >
                                        <span class="font-semibold">Pengguna (Siswa / Operator)</span>
                                        @if($userRole === 'user')
                                            <x-lucide-check class="w-4 h-4 text-emerald-700 shrink-0" />
                                        @endif
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('userRole', 'admin')"
                                        @click="openRoleDropdown = false"
                                        class="w-full flex justify-between items-center text-left px-(--size-16) py-(--size-10) hover:bg-gray-100 cursor-pointer {{ $userRole === 'admin' ? 'bg-emerald-50/70 font-bold text-[#163428]' : '' }}"
                                    >
                                        <span class="font-semibold">Administrator</span>
                                        @if($userRole === 'admin')
                                            <x-lucide-check class="w-4 h-4 text-emerald-700 shrink-0" />
                                        @endif
                                    </button>
                                </div>
                            </div>
                            @error('userRole')
                                <span class="text-xs text-red-500 font-medium leading-none">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Tombol Batal & Simpan (Symmetric Grid Full Width) -->
                        <div class="grid grid-cols-2 gap-(--size-16) w-full pt-2">
                            <button
                                type="button"
                                wire:click="closeUserModal"
                                class="w-full rounded-(--size-16) py-(--size-16) px-(--size-26) bg-(--bg-colour) border-[1.5px] border-(--outline-colour) text-(--text-colour) font-medium hover:bg-(--bg2-colour) cursor-pointer flex items-center justify-center transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="w-full input-button cursor-pointer flex items-center justify-center gap-2 hover:opacity-90 transition-opacity"
                            >
                                @if ($editingUserId)
                                    <x-lucide-square-pen class="w-(--size-16)"/>
                                    <span wire:loading.remove wire:target="saveUser">Simpan Perubahan</span>
                                @else
                                    <x-lucide-user-round-plus class="w-(--size-16)"/>
                                    <span wire:loading.remove wire:target="saveUser">Tambah Pengguna</span>
                                @endif
                                <span wire:loading wire:target="saveUser">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
</div>
