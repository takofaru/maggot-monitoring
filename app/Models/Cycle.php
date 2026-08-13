<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cycle extends Model
{

    protected $fillable = [
        'start_date',
        'end_date',
        'is_active',
        'current_phase',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    // Relasi: Satu Cycle memiliki banyak ObservationLog
    public function observationLogs()
    {
        return $this->hasMany(ObservationLog::class);
    }

    // Relasi: Satu Cycle memiliki banyak EnvironmentLog
    public function environmentLogs()
    {
        return $this->hasMany(EnvironmentLog::class);
    }

}
