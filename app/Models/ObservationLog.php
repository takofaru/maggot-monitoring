<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationLog extends Model
{
    protected $fillable = [
        'cycle_id',
        'phase_name',
        'environment_log_id',
        'timestamp',
        'feed_weight',
        'maggot_weight',
    ];

    protected $casts = [
        'timestamp'     => 'date',
        'feed_weight'   => 'float',
        'maggot_weight' => 'float',
    ];

    public function cycle()
    {
        return $this->belongsTo(Cycle::class, 'cycle_id');
    }

    public function environmentLog()
    {
        return $this->belongsTo(EnvironmentLog::class, 'environment_log_id');
    }
}
