<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Hanya seed akun Administrator untuk deployment.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'full_name'     => 'Administrator',
                'username'      => 'admin',
                'password_hash' => Hash::make('password'),
                'role'          => User::ROLE_ADMIN,
            ]
        );
    }
}
