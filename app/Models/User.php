<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * Modern Mass Assignment Attributes
     */
    protected $fillable = [
        'full_name',
        'username',
        'password_hash',
        'role',
    ];

    /**
     * Attribute tersembunyi
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    /**
     * Beritahu Laravel untuk menggunakan kolom 'password_hash' untuk password autentikasi
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Cek apakah role user adalah admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Cek apakah role user adalah user biasa
     */
    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }
}