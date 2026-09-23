<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPiket extends Model
{
    protected $table = 'jadwal_piket';

    protected $primaryKey = 'id_jadwal_piket';

    protected $fillable = [
        'id_guru',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function guru()
    {
        return $this->belongsTo(Pengguna::class, 'id_guru', 'id_pengguna');
    }
}
