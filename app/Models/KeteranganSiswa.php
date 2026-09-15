<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeteranganSiswa extends Model
{
    protected $table = 'keterangan_siswa';

    protected $fillable = [
        'id_absensi',
        'id_siswa',
        'nama_siswa',
        'kelas',
        'status',
        'keterangan',
        'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function absensi()
    {
        return $this->belongsTo(AbsensiSiswa::class, 'id_absensi', 'id_absensi');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id_siswa');
    }
}
