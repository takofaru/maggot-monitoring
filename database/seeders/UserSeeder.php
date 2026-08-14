<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hash password sekali untuk mempercepat proses seeding
        $defaultPassword = Hash::make('password123');

        $users = [
            // --- Akun Administrator (3 Akun) ---
            [
                'full_name'     => 'Administrator Utama',
                'username'      => 'admin',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_ADMIN,
            ],
            [
                'full_name'     => 'Admin Dua',
                'username'      => 'admin2',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_ADMIN,
            ],
            [
                'full_name'     => 'Supervisor Budidaya',
                'username'      => 'supervisor',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_ADMIN,
            ],

            // --- Akun Pengguna / Siswa / Petugas / Peneliti (15 Akun) ---
            [
                'full_name'     => 'Pengguna Maggot',
                'username'      => 'user',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Siswa Satu',
                'username'      => 'siswa',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Siswa Dua',
                'username'      => 'siswa2',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Budi Santoso',
                'username'      => 'budi',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Siti Rahmawati',
                'username'      => 'siti',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Operator Kandang',
                'username'      => 'operator',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Andi Wijaya',
                'username'      => 'andi',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Dewi Lestari',
                'username'      => 'dewi',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Fajar Nugraha',
                'username'      => 'fajar',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Rina Astuti',
                'username'      => 'rina',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Agus Pratama',
                'username'      => 'agus',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Tri Wahyuni',
                'username'      => 'tri',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Hendra Kusuma',
                'username'      => 'hendra',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Linda Permata',
                'username'      => 'linda',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Rizky Ramadhan',
                'username'      => 'rizky',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Maya Putri',
                'username'      => 'maya',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Eko Prasetyo',
                'username'      => 'eko',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
            [
                'full_name'     => 'Nadia Safitri',
                'username'      => 'nadia',
                'password_hash' => $defaultPassword,
                'role'          => User::ROLE_USER,
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['username' => $userData['username']],
                $userData
            );
        }
    }
}
