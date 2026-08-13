<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnvironmentLog extends Model
{
    use HasFactory;

    // Memastikan model terhubung ke nama tabel yang benar di SQLite
    protected $table = 'environment_logs';

    // Kolom yang diizinkan untuk diisi data
    protected $fillable = [
        'cycle_id',
        'timestamp',
        'temperature',
        'humidity',
    ];

    // Konversi tipe data otomatis saat dipanggil
    protected $casts = [
        'temperature' => 'float',
        'humidity'    => 'float',
        'timestamp'   => 'datetime',
    ];

    // Relasi balik ke Model Cycle
    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }
}
