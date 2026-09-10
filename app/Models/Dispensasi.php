<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispensasi extends Model
{
    protected $table = 'dispensasi';

    protected $primaryKey = 'id_dispensasi';

    protected $fillable = [
    'id_siswa',
    'id_kelas',
    'jenis_dispensasi',
    'tanggal',
    'jam_ke',
    'jam_ke_mulai',
    'jam_ke_selesai',
    'jam_mulai',
    'jam_selesai',
    'alasan',
    'id_guru_piket',
    'status',
];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Siswa yang mendapat dispensasi
    public function siswa()
    {
        return $this->belongsTo(
            Siswa::class,
            'id_siswa',
            'id_siswa'
        );
    }

    // Kelas siswa
    public function kelas()
    {
        return $this->belongsTo(
            Kelas::class,
            'id_kelas',
            'id_kelas'
        );
    }

    // Guru piket yang membuat dispensasi
    public function guruPiket()
    {
        return $this->belongsTo(
            Pengguna::class,
            'id_guru_piket',
            'id_pengguna'
        );
    }
}