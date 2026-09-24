<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispensasi extends Model
{
    protected $table = 'dispensasis';

    protected $primaryKey = 'id_dispensasi';

    // Status kolom plain string
    public const STATUS_MENUNGGU = 'Menunggu Persetujuan';
    public const STATUS_DISETUJUI = 'Disetujui';
    public const STATUS_DITOLAK = 'Ditolak';
    public const STATUS_SELESAI = 'Selesai';

    public const STATUSES = [
        self::STATUS_MENUNGGU,
        self::STATUS_DISETUJUI,
        self::STATUS_DITOLAK,
        self::STATUS_SELESAI,
    ];

    protected $fillable = [
        'id_siswa',
        'id_kelas',
        'id_jadwal',         // Relasi ke jadwal / mapel
        'id_guru',
        'mapel',
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
        'id_wakasek',
        'token',
        'waktu_approval',
        'catatan_wakasek',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Relasi ke model pengguna (sebagai wakasek)
    public function wakasek()
    {
        return $this->belongsTo(
            Pengguna::class,
            'id_wakasek',
            'id_pengguna'
        );
    }

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

    // Relasi ke jadwal / mapel (bisa disesuaikan dengan foreign key yang dipakai di database)
    public function jadwal()
    {
        return $this->belongsTo(
            Jadwal::class,
            'id_jadwal',
            'id_jadwal'
        );
    }

    // Alias atau fungsi tambahan jika ingin menggunakan penamaan jadwalPelajaran
    public function jadwalPelajaran()
    {
        return $this->belongsTo(
            Jadwal::class,
            'id_jadwal',
            'id_jadwal'
        );
    }

    public function guruPengajar()
    {
        return $this->belongsTo(
            Pengguna::class,
            'id_guru',
            'id_pengguna'
        );
    }
}
