<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationLog extends Model
{
<<<<<<< HEAD
    protected $table = 'observation_logs';
}
=======
    protected $fillable = [
        'cycle_id',
        'timestamp',
        'feed_weight',
        'maggot_weight',
    ];

    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }
}
>>>>>>> b4d663f (Simpan perubahan lokal sebelum pull)
