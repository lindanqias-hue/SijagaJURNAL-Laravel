<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuruPiket extends Model
{
    protected $table = 'guru_piket';

    protected $primaryKey = 'id_guru_piket';

    protected $fillable = [
        'id_pengguna',
        'hari',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'tanggal' => 'date',
    ];

    public function pengguna()
    {
        return $this->belongsTo(
            Pengguna::class,
            'id_pengguna',
            'id_pengguna'
        );
    }
}
