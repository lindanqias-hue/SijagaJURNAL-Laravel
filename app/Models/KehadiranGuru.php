<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KehadiranGuru extends Model
{
    protected $table = 'kehadiran_gurus';

    protected $primaryKey = 'id_kehadiran_guru';

    protected $fillable = [
        'id_jadwal',
        'id_guru',
        'tanggal',
        'status',
        'sumber',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal', 'id_jadwal');
    }

    public function guru()
    {
        return $this->belongsTo(Pengguna::class, 'id_guru', 'id_pengguna');
    }
}
