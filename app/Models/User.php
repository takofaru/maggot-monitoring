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

    /**
     * Beritahu Laravel untuk menggunakan kolom 'password_hash' untuk password autentikasi
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}