<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurnal extends Model
{
    protected $table = 'jurnal';

    protected $primaryKey = 'id_jurnal';

    public $timestamps = false;

    protected $fillable = [
        'id_guru',
        'id_kelas',
        'tanggal',
        'jam_ke',
        'materi',
        'jumlah_hadir',
        'jumlah_tidak_hadir',
        'status_kehadiran_guru',
        'catatan',
        'status_validasi',
        'id_validator',
        'tanggal_validasi',
        'catatan_validasi',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_validasi' => 'datetime',
    ];

    // Relasi ke pengguna/guru
    public function guru()
    {
        return $this->belongsTo(
            Pengguna::class,
            'id_guru',
            'id_pengguna'
        );
    }

    // Relasi ke kelas
    public function kelas()
    {
        return $this->belongsTo(
            Kelas::class,
            'id_kelas',
            'id_kelas'
        );
    }
}