<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvironmentLogs extends Model
{
<<<<<<< HEAD
    protected $table = 'environment_logs';
}
=======
    protected $fillable = [
        'cycle_id',
        'timestamp',
        'temperature',
        'humidity',
    ];

    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }
}
>>>>>>> b4d663f (Simpan perubahan lokal sebelum pull)
