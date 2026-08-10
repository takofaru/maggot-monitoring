<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cycle extends Model
{
<<<<<<< HEAD
    protected $table = 'cycles';
=======
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'current_phase',
    ];

    // Relasi: Satu Cycle memiliki banyak ObservationLog
    public function observationLogs()
    {
        return $this->hasMany(ObservationLog::class);
    }

    // Relasi: Satu Cycle memiliki banyak EnvironmentLogs
    public function environmentLogs()
    {
        return $this->hasMany(EnvironmentLogs::class);
    }
>>>>>>> b4d663f (Simpan perubahan lokal sebelum pull)
}
