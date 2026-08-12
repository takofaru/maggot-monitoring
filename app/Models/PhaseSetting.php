<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhaseSetting extends Model
{
    use HasFactory;

    protected $table = 'phase_settings';

    protected $fillable = [
        'order',
        'phase_name',
        'temp_bottom',
        'temp_top',
        'humid_bottom',
        'humid_top',
    ];
}