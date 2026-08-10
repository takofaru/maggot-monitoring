<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
<<<<<<< HEAD
    protected $table = 'users';
}
=======
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'full_name',
        'username',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
    ];

    // Beritahu Laravel untuk menggunakan kolom 'password_hash' untuk autentikasi
    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

  /*   public function getAuthPassword()
    {
        return $this->password_hash;
    } */
}
>>>>>>> b4d663f (Simpan perubahan lokal sebelum pull)
