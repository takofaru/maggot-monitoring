<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhaseSetting extends Model
{
<<<<<<< HEAD
    protected $table = 'phase_settings';
}
=======
    protected $fillable = [
        'order',
        'phase_name',
        'temp_bottom',
        'temp_top',
        'humid_bottom',
        'humid_top',
    ];
}
>>>>>>> b4d663f (Simpan perubahan lokal sebelum pull)
