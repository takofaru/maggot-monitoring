<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationLog extends Model
{

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

