<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiSiswa extends Model
{
    public const STATUS_TANPA_KETERANGAN = 'Tanpa Keterangan';

    protected $table = 'absensi_siswa';

    protected $primaryKey = 'id_absensi';

    protected $fillable = [
        'id_jurnal',
        'id_siswa',
        'keterangan',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id_siswa');
    }

    public function jurnal()
    {
        return $this->belongsTo(Jurnal::class, 'id_jurnal', 'id_jurnal');
    }

    public function keteranganSiswa()
    {
        return $this->hasOne(KeteranganSiswa::class, 'id_absensi', 'id_absensi');
    }
}
